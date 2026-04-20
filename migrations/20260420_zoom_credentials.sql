ALTER TABLE companies
    ADD COLUMN IF NOT EXISTS zoom_account_id     VARCHAR(120) NULL AFTER updated_at,
    ADD COLUMN IF NOT EXISTS zoom_client_id      VARCHAR(120) NULL AFTER zoom_account_id,
    ADD COLUMN IF NOT EXISTS zoom_client_secret  VARCHAR(120) NULL AFTER zoom_client_id;
