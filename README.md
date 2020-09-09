# ϟ StORM
ORM knihovna pro práci s databázi, postavená PDO a lehce integrovatelná s Nette frameworkem

![Travis](https://travis-ci.org/liquiddesign/storm.svg?branch=master)
![Release](https://img.shields.io/github/v/release/liquiddesign/storm.svg?1)

## Dokumentace
☞ [Dropbox paper](https://paper.dropbox.com/doc/StORM--A576bYGiU0wgYv3aYVPU5U4nAg-62jqUghrsrzhpC7WWHwRL)

## TODO
- suffixy mutací vytvářet pomocí callbacku, aby mohlo vstupovat sekvenční pole mutací
- odstranit Model classu
- eventy na delete, update, insert - priklad pro logger
- generator entit 
- validace entit vzhledem k databazi
- tracydebug -> link na soubor z kteryho se to vola + proklik, explain
- kdyz nema Entita zadnou anotaci spadne to, dedicnost ?
---------------------------
PHPSTAN error pri Ramissio DB user 
/** @var \App\Eshop\DB\PositionsRepository $positionsRepository */
$positionsRepository = $this->getConnection()->findRepository(Positions::class);
$positionsRepository->many($positionId)->first()->acronym; // error
$positionsRepository->one($positionId)->acronym;