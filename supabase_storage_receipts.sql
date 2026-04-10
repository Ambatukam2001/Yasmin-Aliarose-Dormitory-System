-- Optional: receipt uploads from the booking flow (Supabase Storage)
-- 1. In Dashboard → Storage → New bucket → id: receipts → Public bucket ON
-- 2. Then run (adjust if your bucket name differs):

INSERT INTO storage.buckets (id, name, public)
VALUES ('receipts', 'receipts', true)
ON CONFLICT (id) DO NOTHING;

-- Allow anonymous upload/read (dev only — tighten for production)
CREATE POLICY "receipts public read"
ON storage.objects FOR SELECT
TO public
USING (bucket_id = 'receipts');

CREATE POLICY "receipts public insert"
ON storage.objects FOR INSERT
TO public
WITH CHECK (bucket_id = 'receipts');
