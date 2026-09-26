# Future Voices School & Studio — database image.
# The official MySQL image runs any *.sql file placed in
# /docker-entrypoint-initdb.d/ automatically, but only the FIRST time it
# starts with an empty data directory (i.e. an empty Render disk) — so
# this seeds rooms/programs (schema.sql) once, and every restart after
# that just reuses the existing data on the disk.
FROM mysql:8

COPY schema.sql /docker-entrypoint-initdb.d/schema.sql
