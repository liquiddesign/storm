Typove vse:)
--------------------------------------
3. Moznosti filtrace
filter bude obsahovat seznam filtru, moznost stranek a zakladni abstrakni funkci ktera se vola
filter -> prohodit parametry a kdyztak udela many -> getFilteredCollection()
findAll() =: pouvazovat -> filterProvidet bad
Opravit
filter -> Expected parameter of type '\mixed[][]', 'int[]|null[]' provided
-> co tak zpet pridat do RElation Collection
---------------------------------------
4. projit chby uvnitr a dat throwy ?
---------------------------------------

---------------------------------------

----------------------------------------
-----------------------------------------

------------------------------------------

------------------------------------------

 

DOKUMENTACE
-------------
vzory jak vracet Collection a first v repozitarich viz ramissio
findRepository
z filterByColumns | propertiesToColumns
getRepository() jinak pres container ->getService
Interface relations ICartItem navrh
CZ manual
Vypnout caching pass parent to Entity -> napsat navod
custom anotace
isEmpty
new News('uuid')
Parents fetchCOlumn
mutations
anotace constraint se pisi s tabulkami
AnnotationException ? zbytecne, jiny zpusob validace -> vyhazuji structure
syncAffected number
vkladani podbjektu
moznost zalozit podobjekt v jednom callu

$pageRepo->createPage([
   ...
  'sitemap' => [
    'lastmod' => '2005-01-01',
    'changefreq' => 'monthly',
    'priority' => 0.5
    ],
    'categorues' [
        'psi','kocky'
    ]
]);