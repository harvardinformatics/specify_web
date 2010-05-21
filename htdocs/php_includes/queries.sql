create table if not exists web_quicksearch (
  quicksearchid bigint primary key not null auto_increment,
  collectionobjectid bigint not null,
  searchable text
) ENGINE MyISAM CHARACTER SET utf8;
create fulltext index i_web_quicksearch on web_quicksearch(searchable);


insert into web_quicksearch (collectionobjectid, searchable) (
   select a.collectionobjectid, concat(geography.name, ' ', gloc.name, ' ', a.fullname, ' ', a.catalognumber) 
   from geography, 
       (select distinct taxon.fullname, locality.geographyid geoid, collectionobject.altcatalognumber as catalognumber, collectionobject.collectionobjectid 
        from collectionobject 
            left join fragment on collectionobject.collectionobjectid = fragment.collectionobjectid 
            left join determination on fragment.fragmentid = determination.fragmentid 
            left join taxon on determination.taxonid = taxon.taxonid 
            left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid 
            left join locality on collectingevent.localityid = locality.localityid 
        ) a
   left join geography gloc on a.geoid = gloc.geographyid 
   where geography.rankid = 200 
     and geography.highestchildnodenumber >= a.geoid 
     and geography.nodenumber <= a.geoid);


create table if not exists web_search (
    searchid bigint primary key not null auto_increment,
    collectionobjectid bigint not null,
    family varchar (255),
    genus varchar (255),
    species varchar (255),
    infraspecific varchar (255),
    author text,
    country varchar (255),
    location text,
    typestatus varchar (255),
    state varchar(255),
    county varchar (255),
    datecollected date,
    collector text,
    collectornumber varchar (255),
    specimenimages boolean,
    herbaria varchar (255),
    ex_title text,
    ex_author text,
    ex_number varchar (255),
    subcollection varchar (255),
    barcode varchar (255),
    yearpublished varchar(255),
    groupfilter varchar (50),
    taxon_highestchild int,
    taxon_nodenumber int,
    geo_highestchild int,
    geo_nodenumber int
) ENGINE MyISAM CHARACTER SET utf8;

-- add index to taxon(rankid) to find particular ranks
create index taxon_rankid on taxon(rankid);

delimiter | 
create function specify6.getHigherTaxonOfRank(rank_id INT, highestchild INT, nodenum INT) 
returns VARCHAR(255)
BEGIN
   declare t_name varchar(255);
   select name into t_name from taxon  where highestchildnodenumber >= highestchild and nodenumber <= nodenum and rankid = rank_id;   
   return t_name;
END |
delimiter ;

delimiter | 
create function specify6.getGeographyOfRank(rank_id INT, highestchild INT, nodenum INT) 
returns VARCHAR(255)
BEGIN
   declare t_name varchar(255);
   select name into t_name from geography where highestchildnodenumber >= highestchild and nodenumber <= nodenum and rankid = rank_id;   
   return t_name;
END |
delimiter ;


--insert into web_search (family,genus,species,author,collectionobjectid,barcode) select getParentRank(140,t.highestchildnodenumber,t.nodenumber) as family, getParentRank(180,t.highestchildnodenumber,t.nodenumber) as genus, t.name, t.author, c.collectionobjectid, c.altcatalognumber from determination d left join taxon t on d.taxonid = t.taxonid left join fragment f on d.fragmentid = f.fragmentid left join collectionobject c on f.collectionobjectid = c.collectionobjectid where t.rankid = 220;
-- species rank
insert into web_search (taxon_highestchild,taxon_nodenumber,species,author,collectionobjectid,barcode) select t.highestchildnodenumber,t.nodenumber, t.name, t.author, c.collectionobjectid, c.altcatalognumber from determination d left join taxon t on d.taxonid = t.taxonid left join fragment f on d.fragmentid = f.fragmentid left join collectionobject c on f.collectionobjectid = c.collectionobjectid where t.rankid = 220;


--insert into web_search (family,genus,species,infraspecific,author,collectionobjectid,barcode) select getHigherTaxonOfRank(140,t.highestchildnodenumber,t.nodenumber) as family, getHigherTaxonOfRank(180,t.highestchildnodenumber,t.nodenumber) as genus, getParentRank(220,t.highestchildnodenumber,t.nodenumber), t.name, t.author, c.collectionobjectid, c.altcatalognumber from determination d left join taxon t on d.taxonid = t.taxonid left join fragment f on d.fragmentid = f.fragmentid left join collectionobject c on f.collectionobjectid = c.collectionobjectid where t.rankid > 220;
-- below species rank
insert into web_search (taxon_highestchild,taxon_nodenumber,infraspecific,author,collectionobjectid,barcode) 
select t.highestchildnodenumber,t.nodenumber, t.name, t.author, c.collectionobjectid, c.altcatalognumber 
from determination d left join taxon t on d.taxonid = t.taxonid 
left join fragment f on d.fragmentid = f.fragmentid 
left join collectionobject c on f.collectionobjectid = c.collectionobjectid 
where t.rankid > 220;

-- genera
insert into web_search (taxon_highestchild,taxon_nodenumber,genus,author,collectionobjectid,barcode)  
select t.highestchildnodenumber,t.nodenumber, t.name, t.author, c.collectionobjectid, c.altcatalognumber  
from determination d left join taxon t on d.taxonid = t.taxonid  left join fragment f on d.fragmentid = f.fragmentid  left join collectionobject c on f.collectionobjectid = c.collectionobjectid  
where t.rankid = 180;

create index idx_websearch_taxon_highestchild on web_search(taxon_highestchild);
create index idx_websearch_taxon_nodenumber on web_search(taxon_nodenumber);

-- create temporary copy of taxonomy tree in a myisam table.
create table temp_taxon engine myisam as select taxonid, name, highestchildnodenumber, nodenumber, rankid from taxon;
create index temp_taxon_hc on temp_taxon(highestchildnodenumber);
create index temp_taxon_node on temp_taxon(nodenumber);
create index temp_taxon_rank on temp_taxon(rankid);

update web_search, temp_taxon set family = temp_taxon.name 
  where highestchildnodenumber >= taxon_highestchild 
    and nodenumber <= taxon_nodenumber 
    and rankid = 140 
    and family is null;
    
update web_search, temp_taxon set genus = temp_taxon.name 
  where highestchildnodenumber >= taxon_highestchild 
    and nodenumber <= taxon_nodenumber 
    and rankid = 180 
    and genus is null;    

--update web_search set family = getHigherTaxonOfRank(140,taxon_highestchild,taxon_nodenumber), 
--                      genus = getHigherTaxonOfRank(180,taxon_highestchild,taxon_nodenumber);

-- family and above
insert into web_search (taxon_highestchild,taxon_nodenumber,family,genus,author,collectionobjectid,barcode)  
select t.highestchildnodenumber,t.nodenumber, t.name, '[None]', t.author, c.collectionobjectid, c.altcatalognumber  
from determination d left join taxon t on d.taxonid = t.taxonid  left join fragment f on d.fragmentid = f.fragmentid  left join collectionobject c on f.collectionobjectid = c.collectionobjectid  
where t.rankid <= 180;

-- Denormalize geography 
-- locality
update web_search 
   left join  collectionobject c on web_search.collectionobjectid = c.collectionobjectid 
   left join collectingevent e on c.collectingeventid = e.collectingeventid 
   left join locality l on e.localityid = l.localityid 
   left join geography g on l.geographyid = g.geographyid
   set web_search.location = 
       concat(coalesce(l.localityname,''), ' ', coalesce(e.verbatimlocality,'')), 
       web_search.geo_highestchild = g.highestchildnodenumber, 
       web_search.geo_nodenumber = g.nodenumber;

-- create temporary copy of geography tree in a myisam table.
create table temp_geography (geographyid int primary key, name varchar(64), highestchildnodenumber int, nodenumber int, rankid int) 
    engine myisam 
    as select geographyid, name, highestchildnodenumber, nodenumber, rankid from geography;
create index temp_geography_hc on temp_geography(highestchildnodenumber);
create index temp_geography_node on temp_geography(nodenumber);
create index temp_geography_rank on temp_geography(rankid);

-- populate the country field
update web_search, temp_geography set country = temp_geography.name 
  where geo_highestchild <= highestchildnodenumber 
    and geo_nodenumber >= nodenumber  
    and rankid = 200 
    and country is null;
    
-- populate the state field    
update web_search, temp_geography set state = temp_geography.name 
  where geo_highestchild <= highestchildnodenumber 
    and geo_nodenumber >= nodenumber  
    and rankid = 300 
    and state is null;    

-- populate the county field    
update web_search, temp_geography set county = temp_geography.name 
  where geo_highestchild <= highestchildnodenumber 
    and geo_nodenumber >= nodenumber  
    and rankid = 400
    and county is null;
    
--update web_search w left join collectionobject c on w.collectionobjectid = c.collectionobjectid left join collectingevent e on c.collectingeventid = e.collectingeventid left join locality l on e.localityid = l.localityid left join geography g on l.geographyid = g.geographyid 
--   set w.country = getGeographyOfRank(200,g.highestchildnodenumber,g.nodenumber);

--update web_search w left join collectionobject c on w.collectionobjectid = c.collectionobjectid left join collectingevent e on c.collectingeventid = e.collectingeventid left join locality l on e.localityid = l.localityid left join geography g on l.geographyid = g.geographyid 
--   set w.state = getGeographyOfRank(300,g.highestchildnodenumber,g.nodenumber);

--update web_search w left join collectionobject c on w.collectionobjectid = c.collectionobjectid left join collectingevent e on c.collectingeventid = e.collectingeventid left join locality l on e.localityid = l.localityid left join geography g on l.geographyid = g.geographyid 
--   set w.county = getGeographyOfRank(400,g.highestchildnodenumber,g.nodenumber);
   
create index idx_websearch_family on web_search(family);
create index idx_websearch_genus on web_search(genus);
create index idx_websearch_species on web_search(species);
create index idx_websearch_infraspecific on web_search(infraspecific);
create index idx_websearch_author on web_search(author);
create index idx_websearch_country on web_search(country);
create index idx_websearch_location on web_search(location);
create index idx_websearch_state on web_search(state);
create index idx_websearch_county on web_search(county);
create index idx_websearch_typestatus on web_search(typestatus);
create index idx_websearch_collector on web_search(collector);
create index idx_websearch_collectornumber on web_search(collectornumber);
create index idx_websearch_herbaria on web_search(herbaria);
create index idx_websearch_barcode on web_search(barcode);
create index idx_websearch_datecollected on web_search(datecollected);
