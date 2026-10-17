-- Run once in the Neon SQL editor: the "Valid until" date of bidder documents (permits, clearances, PhilGEPS).
ALTER TABLE bidder_documents ADD COLUMN IF NOT EXISTS valid_until date NULL;
