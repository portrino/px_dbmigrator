# TYPO3 extension `px_dbmigrator`

[![Latest Stable Version](https://poser.pugx.org/portrino/px_dbmigrator/v/stable)](https://packagist.org/packages/portrino/px_dbmigrator)
[![TYPO3 10](https://img.shields.io/badge/TYPO3-10-orange.svg)](https://get.typo3.org/version/10)
[![TYPO3 11](https://img.shields.io/badge/TYPO3-11-orange.svg)](https://get.typo3.org/version/11)
[![TYPO3 12](https://img.shields.io/badge/TYPO3-12-orange.svg)](https://get.typo3.org/version/12)
[![Total Downloads](https://poser.pugx.org/portrino/px_dbmigrator/downloads)](https://packagist.org/packages/portrino/px_dbmigrator)
[![Monthly Downloads](https://poser.pugx.org/portrino/px_dbmigrator/d/monthly)](https://packagist.org/packages/portrino/px_dbmigrator)

> Database Migrator for TYPO3

## 1 Features

Today TYPO3 projects are mostly developed and deployed using git. The **PxDbmigrator** extension can help you to work 
with SQL changes in this environment. Apply SQL changes on all developer instances or even deploy changes to your 
Production or Staging systems.

### 1.1 What it does

The **PxDbmigrator** extension provides a command that executes migration files (e.g. `*.sql` files or `*.sh` or 
`*.typo3cms` files) that you place in a configurable directory. 
Once the command is called it checks for new migration files and "executes" them in the given order.

So if you want to distribute a SQL Command (e.g. an INSERT/ REPLACE statement for a new record) across your 
installations, just create a file with a unique name and push it into your repository. Once others pull it and execute 
the **PxDbmigrator** command, they will have your changes applied!

It's recommended to automate the execution of the command using composer pre- or post- scripts.

## 2 Usage

### 2.1 Installation

#### Installation using Composer

The **recommended** way to install the extension is using [composer](https://getcomposer.org/).

Run the following command within your Composer based TYPO3 project:

```
composer require portrino/px_dbmigrator
```

#### Installation as extension from TYPO3 Extension Repository (TER)

Download and install the [extension](https://extensions.typo3.org/extension/px_dbmigrator) with the extension manager
module.

### 2.2 Setup

After finishing the installation, head over to the extension settings and adjust the given settings to your needs.

So, a possible configuration in the `LocalConfiguration.php` of a composer managed TYPO3 installation could look like:

```
return [

    ...
    
    'EXTENSIONS' => [
    
        ...
        
        'px_dbmigrator' => [
            'migrationFolderPath' => '../migrations',
            'mysqlBinaryPath' => '/usr/bin/mysql',
            'typo3cmsBinaryPath' => '/vendor/bin/typo3',
        ],
        
        ...
        
    ],
    
    ...
    
];
```

### 2.3 Invoke

Use the TYPO3 CLI to invoke the command to execute the migrations:

```
./vendor/bin/typo3 migration:migrateall
```


## 3 Troubleshooting

If the execution of a migration file fails, the associated registry entry of the last executed file won't be stored in 
the database and the file will be executed again on the next run. Notice that it already might have done changes to your
database before it crashed!

If you want to see which migration was executed last, you can check the database table `sys_registry` with the namespace
`PxDbmigrator`.

## 4 FAQ

#### Can I use timestamps as migration numbers/ filename prefix?

Sure. This is indeed the recommended way to do it, as the filename already contains the date of creation and the files 
are sorted correctly by name.

#### Do I need the leading zeros?

No, you can also use `1.sql` and so on. But as already mentioned above, the recommended way is to use timestamps.
Example: `1696560849-adds-some-required-pages.sql`

#### Can I undo a migration?

No, the migrator only knows one direction. You’ll need to do it manually.

## 5 Authors

* See the list of [contributors](https://github.com/portrino/px_dbmigrator/graphs/contributors) who participated in this project.
