# StORM
StORM is lightweight PHP ORM library based on PDO, fast alternative to Doctrine

![Travis](https://travis-ci.org/liquiddesign/storm.svg?branch=master)
![Release](https://img.shields.io/github/v/release/liquiddesign/storm.svg)

## FEATURES
- works great with Nette and others framework, even standalone
- clean, smarter code, that is more understandable and extendable
- keep your database model organized and easy to extend and reusable
- ORM optimizations, DI pattern, and "autojoin" feature
- prepare for localized web applications
- extension for API (for you REACT, apps) (UNDER CONSTRUCTION)
- extension from database schema synchronization (UNDER CONSTRUCTION)
- well tested, documented

## REQUIREMENTS
- PHP >=7.3.0  
- EXT PDO
- EXT JSON
- NETTE DI >=3.0.0
- NETTE CACHING >=3.0.0  
- TRACY >=2.7.0

## INSTALLATION
> composer require liquiddesign/storm

## DOCUMENTATION
https://paper.dropbox.com/doc/StORM--Awb3TaGQMzsId3ZrkFM0gAWZAg-hVi2MGVyIC8j1bN66dUKY

## API
http://www.phpstorm.net/api

## WEB
http://www.phpstorm.net

## USAGE
### WITH NETTE FRAMEWORK
1. Add extension and configuration to neon config
```php
// file: config.neon

extensions:
   storm: \StORM\Bridges\StormDI

storm:
   default:
      host: localhost
      dbname: default
      user: default
      password: "****"
      debug: true
```
2. Let's go
You will find autowired service by class \StORM\Connection or by name "storm.default".  
Follow DI rules in Nette: https://doc.nette.org/cs/3.0/di-usage

```php
// get 10 rows on page 2 from table "users" where "age" >= 18
$users = $storm->rows(['users'])->where('age >= :age', ['age' => 18])->page(2, 10);
foreach ($users as $user) {
  echo $user->age;
}
```

### STANDALONE USAGE
1. Create config file
```php
services:
	- Nette\Caching\Storages\DevNullStorage
storm:
	default:
		host: localhost
		dbname: _test_storm
		user: root
		password: ""
		driver: mysql
```
2. Create temporary directory

3. Let's go
```php
$config = __DIR__ . '/config.neon';
$tempDir = __DIR__ . '/temp';
		
$loader = new \Nette\DI\ContainerLoader($tempDir);
$class = $loader->load(static function (\Nette\DI\Compiler $compiler) use ($config): void {
    $compiler->addExtension('storm', new \StORM\Bridges\StormDI());
    $compiler->loadConfig($config);
});

/** @var \Nette\DI\Container $container */
$container = new $class();
$storm = $container->getByType(Connection::class);
```

```php
// get 10 rows on page 2 from table "users" where "age" >= 18
$users = $storm->rows(['users'])->where('age >= :age', ['age' => 18])->page(2, 10);
foreach ($users as $user) {
  echo $user->age;
}
```






