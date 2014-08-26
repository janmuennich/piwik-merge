Piwik Merge
===========

Script to merge two Piwik databases or import sites from another Piwik.  Upgraded and tested to work with Piwik 2.5.0.

### Usage:
##### 1. Update references to old and new Piwik databases
```php
$db_old = array(
  'server' => 'localhost',
  'user' => '',
  'pass' => '',
  'db' => '',
  'prefix' => 'piwik_',
);

$db_new = array(
  'server' => 'localhost',
  'user' => '',
  'pass' => '',
  'db' => '',
  'prefix' => 'piwik_',
);
```
##### 2. Input how you'd like to import or merge sites IDs from one instance to the next.
```php
$import_sites = array(2=>0);
```
##### 3. Run!
```bash
php piwik_merge.php
```

### References:
- http://www.jan-muennich.de/merging-two-piwik-installations
- http://www.jan-muennich.de/importing-piwik-for-1-8-2
