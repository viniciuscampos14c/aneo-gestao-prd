<?php

/**
 * ZoomService — integração com a API do Zoom via Server-to-Server OAuth.
 *
 * Pré-requisito: criar um app "Server-to-Server OAuth" no Zoom Marketplace
 * (marketplace.zoom.us → Build App → Server-to-Server OAuth) com scope
 * meeting:write:admin e salvar Account ID, Client ID e Client Secret
 * nas configurações da empresa no ERP.
 */
class ZoomService
{
    private string $accountId;
    private string $clientId;
    private string $clientSecret;

    private const TOKEN_URL   = 'https://zoom.us/oauth/token';
    private const MEETING_URL = 'https://api.zoom.us/v2/users/me/meetings';

    public function __construct(string $accountId, string $clientId, string $clientSecret)
    {
        $this->accountId    = $accountId;
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
    }

    // -------------------------------------------------------------------------
    // Autenticação
    // -------------------------------------------------------------------------

    /**
     * Obtém um Bearer token via Server-to-Server OAuth.
     * O token é válido por 1 hora; não fazemos cache pois cada request
     * já reutiliza o token dentro da mesma requisição PHP.
     *
     * @throws RuntimeException se a API retornar erro
     */
    private function getAccessToken(): string
    {
        $url  = self::TOKEN_URL . '?grant_type=account_credentials&account_id=' . urlencode($this->accountId);
        $auth = base64_encode($this->clientId . ':' . $this->clientSecret);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '',
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . $auth,
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new RuntimeException('Zoom OAuth: erro cURL — ' . $curlErr);
        }

        $data = json_decode((string) $response, true);

        if ($httpCode !== 200 || empty($data['access_token'])) {
            $msg = $data['reason'] ?? ($data['message'] ?? 'Erro desconhecido');
            throw new RuntimeException('Zoom OAuth falhou (' . $httpCode . '): ' . $msg);
        }

        return (string) $data['access_token'];
    }

    // -------------------------------------------------------------------------
    // Reuniões
    // -------------------------------------------------------------------------

    /**
     * Cria uma reunião agendada no Zoom.
     *
     * @param string $topic       Título da reunião
     * @param string $startTime   Horário no formato 'Y-m-d H:i' (fuso America/Sao_Paulo)
     * @param int    $durationMin Duração em minutos
     *
     * @return array{
     *   meeting_id: string,
     *   join_url:   string,
     *   password:   string,
     *   start_url:  string,
     *   raw:        string
     * }
     *
     * @throws RuntimeException se a API retornar erro
     */
    public function createMeeting(string $topic, string $startTime, int $durationMin): array
    {
        $token = $this->getAccessToken();

        // Converte horário de Brasília → UTC (Zoom exige ISO 8601 UTC)
        $dt = new DateTime($startTime, new DateTimeZone('America/Sao_Paulo'));
        $dt->setTimezone(new DateTimeZone('UTC'));
        $startUtc = $dt->format('Y-m-d\TH:i:s\Z');

        $body = json_encode([
            'topic'      => $topic,
            'type'       => 2,                       // 2 = reunião agendada
            'start_time' => $startUtc,
            'duration'   => $durationMin,
            'timezone'   => 'America/Sao_Paulo',
            'settings'   => [
                'waiting_room'         => false,
                'join_before_host'     => true,
                'mute_upon_entry'      => true,
                'participant_video'    => false,
                'host_video'           => true,
                'auto_recording'       => 'cloud',
            ],
        ]);

        $ch = curl_init(self::MEETING_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new RuntimeException('Zoom API: erro cURL — ' . $curlErr);
        }

        $data = json_decode((string) $response, true);

        if ($httpCode !== 201 || empty($data['id'])) {
            $msg = $data['message'] ?? ('HTTP ' . $httpCode);
            throw new RuntimeException('Zoom createMeeting falhou: ' . $msg);
        }

        return [
            'meeting_id' => (string) $data['id'],
            'join_url'   => (string) ($data['join_url']   ?? ''),
            'password'   => (string) ($data['password']   ?? ''),
            'start_url'  => (string) ($data['start_url']  ?? ''),
            'raw'        => (string) $response,
        ];
    }

    /**
     * Cancela (deleta) uma reunião no Zoom.
     *
     * @param string $meetingId ID da reunião retornado pela API
     * @return bool true se deletada com sucesso (204) ou se já não existe (404)
     */
    public function deleteMeeting(string $meetingId): bool
    {
        $token = $this->getAccessToken();
        $url   = 'https://api.zoom.us/v2/meetings/' . urlencode($meetingId);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new RuntimeException('Zoom deleteMeeting: erro cURL — ' . $curlErr);
        }

        // 204 = deletado; 404 = não encontrado (já deletado no Zoom)
        return in_array($httpCode, [204, 404], true);
    }
}
