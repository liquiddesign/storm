# ϟ StORM
ORM knihovna pro práci s databázi, postavená PDO a lehce integrovatelná s Nette frameworkem

![Travis](https://travis-ci.org/liquiddesign/storm.svg?branch=master)
![Release](https://img.shields.io/github/v/release/liquiddesign/storm.svg?1)

## Dokumentace
☞ [Dropbox paper](https://paper.dropbox.com/doc/StORM--A576bYGiU0wgYv3aYVPU5U4nAg-62jqUghrsrzhpC7WWHwRL)

## TODO
- kdyz je relationNxN jinyho typu hodit error

- $product->tags->relate($values['tags'], true); // dat primo do update apodobne, moznost dalsich hodnot update

- sql error neukaze kolekci neni to vyjimka kolekce

- relate na prazdny seznam klicu musi hodit error

- lip Unknown property/column 'translated' in 'array'. Fix typo or bind property by @column in array

- zrusit isStored + spoji Update updateAll u entity (sequencni pole), loadFromEntity -> pryc

- kdyby fungovalo update (['name' => 'test', 'account' => ['login' => true]]) -> prepsat at se pouzije sync at je to shodne

- dump debug metody prenyst mimo kod do staticke metody

- suffixy mutací vytvářet pomocí callbacku, aby mohlo vstupovat sekvenční pole mutací

- eventy na delete, update, insert - priklad pro logger

- tracydebug -> link na soubor z kteryho se to vola + proklik, explain

- kdyz nema Entita zadnou anotaci spadne to, dedicnost ?

- Dokazat vložit:
"INSERT INTO member_relations (member_id,member_level,upline_member_id,upline_member_level,max_position_between,max_position_between_upper) SELECT '$member->uuid', '$level', pr.upline_member_id, pr.upline_member_level, pr.max_position_between, pr.max_position_between_upper FROM member_relations pr WHERE pr.member_id = '" . $userValues['sponsorId'] . "';"

- Vylepšení literálu
$sub = new Literal("SELECT MAX(count) FROM users WHERE name=:name",["name"=>"Petr"]);
tady je to podle dokumentace ale pak dojde k chybě že konstruktor bere jen 1 argument


- sem tam je potreba konvertovat ze vstupu '' => null, napriklad kdyz je typ datetime, mohlo by to umet automaticky nebo zadat