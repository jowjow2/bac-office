-- Run once in the Neon Query editor if the deploy did not run the migration by itself:
-- adds the column behind the NEW mark of Purchase requests, and counts every request that exists now as seen.
DO $$
BEGIN
ALTER TABLE procurement_requests ADD COLUMN IF NOT EXISTS seen_at timestamp NULL;
UPDATE procurement_requests SET seen_at = now() WHERE seen_at IS NULL;
END $$;
