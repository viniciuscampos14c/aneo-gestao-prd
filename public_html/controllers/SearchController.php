<?php

class SearchController extends BaseController
{
    private SearchModel $search;

    public function __construct()
    {
        $this->search = new SearchModel();
    }

    public function index(): void
    {
        require_auth();

        $query = trim((string) request('q', ''));
        $results = $query !== '' ? $this->search->run($query) : [
            'students' => [],
            'leads' => [],
            'invoices' => [],
            'courses' => [],
        ];

        $this->render('generic/search', [
            'title' => 'Busca Global',
            'query' => $query,
            'results' => $results,
        ]);
    }
}
