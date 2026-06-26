# Modular Feature Directory

Place self-contained modules in this directory. Each module should expose its own controllers, models, migrations, routes and configuration via a `module.json` manifest to allow versioning and easy upgrades.

Recommended structure for a module:

```
modules/
  Student/
    module.json
    Http/
      Controllers/
    Models/
    Database/
      Migrations/
    Resources/
      views/
```

Modules can register routes and services during bootstrap by listing their service provider class in the manifest.
