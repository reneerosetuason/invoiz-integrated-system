# Rider database reference

The rider and buyer clients share the Laravel backend at the repository root.
The rider app uses `/api/logistics`; it never connects directly to MySQL and
does not need a separate database, database credentials, or a PHP server here.

The authoritative schema changes are in the root `database/migrations` folder.
Use Laravel migrations against the configured shared database after reviewing
the actual missing columns. Do not import this folder's `schema.sql`, account
seed, or historical SQL update scripts into an existing INVOIZ database.
Several of those scripts overlap existing migrations and are not idempotent.
They remain available as reference from the original rider project.

The integration audit found the existing rider, delivery, notification,
messaging, earnings, remittance, application, and vehicle tables already
present. A guarded root migration repairs missing nullable rider licence and
vehicle registration fields without overwriting existing records.

See [the rider README](../README.md) for API configuration and running the app.
