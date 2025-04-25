## Data-Centric Manifesto
Data-Centric Manifesto je myšlenka, která prosazuje návrh a optimalizaci softwarových systémů s důrazem na data. Tvrdí, že jádrem vývoje softwaru není kód, ale kvalita, struktura a konzistence dat. Hlavním bodem této deklarace je, že data jsou nejdůležitějším aktivem v systému, jejich životní cyklus je obvykle delší než životnost kódu, a proto je nutné přistupovat k návrhu a tvorbě systémů s ohledem na data jako středobod.

Upgradoval jsem můj datovou strukturu, aby splnil požadavky Data-Centric Manifesto. Zde popíšu změny, které jsem provedl.

### Product Table
Přidal jsem sku, attributes, version, currency_id, created_at a updated_at sloupce.
Sice sloupec id slibuje jednoznačnost produktu, ale sku slibuje nejen jednoznačnost ale také zároveň dokáže najít a uložit všechny historické verze změn informace produktů. 

#### Version
Version popisuje verze produktu, defaultně je 1. Kdyby uživatel změnil nějaké informace produktu, tak automaticky uloží jako nový produkt s novými informací a stejný sku, verze se zvýšuje o 1. Má takovou funkci, že uživatele si může vrátit kdykoliv na původní verze, data se neztratí.

#### Attributes
Attributes umožňuje uživatel přidat jiné parametry a informace produktu, aby program byl flexibilnější. 

#### currency_id
currency_id popisuje jakou měnu má cena produktu.

#### created_at
created_at popisuje čas, kdy je ten produkt vytvořen.

#### updated_at
updated_at popisuje verze produktu, kdy uživatel provedl změny informace nebo parametru.