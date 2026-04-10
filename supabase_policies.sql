-- Optional: enable RLS with permissive policies for anon (public) key.
-- If you use these, remove DISABLE from supabase_schema.sql and run:

-- ALTER TABLE bookings ENABLE ROW LEVEL SECURITY;
-- CREATE POLICY bookings_select ON bookings FOR SELECT TO anon USING (true);
-- CREATE POLICY bookings_insert ON bookings FOR INSERT TO anon WITH CHECK (true);
-- CREATE POLICY bookings_update ON bookings FOR UPDATE TO anon USING (true) WITH CHECK (true);
-- CREATE POLICY bookings_delete ON bookings FOR DELETE TO anon USING (true);

-- Same pattern for rooms / beds if you enable RLS there.
