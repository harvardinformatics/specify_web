-- Queries to build and populate specify 6 botany denormalized
-- tables for searching the database over the web.

-- Table containing full text index on a subset of fields to 
-- use MySQL's full text index capabilities for a 'quick search'
create table if not exists web_quicksearch (
  quicksearchid bigint primary key not null auto_increment,
  collectionobjectid bigint not null,
  searchable text
) ENGINE MyISAM CHARACTER SET utf8;
create fulltext index i_web_quicksearch on web_quicksearch(searchable);

delete from web_quicksearch;

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


-- Table to hold denormalized copy of key fields for searches, particularly
-- levels from the taxonomic and geographic heirarchies.  
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
    datecollected varchar(30),
    yearcollected varchar(4),
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

delete from web_search;

-- Add indexes to specify tables: 
-- add index to taxon(rankid) to find particular ranks
create index idx_taxon_rankid on taxon(rankid);
-- other indexes 
create index idx_agent_specialty_role on agentspecialty(role);
create index idx_agent_specialty_name on agentspecialty(specialtyname);
create index idx_taxontreedefitem_name on taxontreedefitem(name);


-- Queries that follow are designed and optimized for speed.  Fewer queries with more complex joins are 
-- possible, but much less effiecient, particularly when updates involving selects from the native InnoDB
-- tables to update rows in the web_search table are involved. 

-- species rank
-- catalognumber = barcode is in fragment.identifier (primary)
-- or preparation.identifier (secondary for complex objects).
-- in HUH specify botany, collectionobject.altcatalognumber is the ASA specimen.specimenid
insert into web_search 
  (taxon_highestchild,taxon_nodenumber,species,author,collectionobjectid,barcode) 
  select t.highestchildnodenumber,t.nodenumber, t.name, t.author, 
         c.collectionobjectid, f.identifier
         from 
         determination d left join taxon t on d.taxonid = t.taxonid 
         left join fragment f on d.fragmentid = f.fragmentid 
         left join collectionobject c on f.collectionobjectid = c.collectionobjectid
          left join taxontreedefitem td on t.rankid = td.rankid 
         where td.name = 'Species' and f.identifier is not null;

insert into web_search 
  (taxon_highestchild,taxon_nodenumber,species,author,collectionobjectid,barcode) 
  select t.highestchildnodenumber,t.nodenumber, t.name, t.author, 
         c.collectionobjectid, p.identifier
         from 
         determination d left join taxon t on d.taxonid = t.taxonid 
         left join fragment f on d.fragmentid = f.fragmentid
         left join preparation p on f.preparationid = p.preparationid 
         left join collectionobject c on f.collectionobjectid = c.collectionobjectid 
         left join taxontreedefitem td on t.rankid = td.rankid 
         where td.name = 'Species' and p.identifier is not null;         
         
--insert into web_search (family,genus,species,infraspecific,author,collectionobjectid,barcode) select getHigherTaxonOfRank(140,t.highestchildnodenumber,t.nodenumber) as family, getHigherTaxonOfRank(180,t.highestchildnodenumber,t.nodenumber) as genus, getParentRank(220,t.highestchildnodenumber,t.nodenumber), t.name, t.author, c.collectionobjectid, c.altcatalognumber from determination d left join taxon t on d.taxonid = t.taxonid left join fragment f on d.fragmentid = f.fragmentid left join collectionobject c on f.collectionobjectid = c.collectionobjectid where t.rankid > 220;
-- below species rank
insert into web_search (taxon_highestchild,taxon_nodenumber,infraspecific,author,collectionobjectid,barcode) 
   select t.highestchildnodenumber,t.nodenumber, t.name, t.author, c.collectionobjectid, f.identifier
     from determination d left join taxon t on d.taxonid = t.taxonid 
     left join fragment f on d.fragmentid = f.fragmentid 
     left join collectionobject c on f.collectionobjectid = c.collectionobjectid 
     where t.rankid > 220 and f.identifier is not null;

insert into web_search (taxon_highestchild,taxon_nodenumber,infraspecific,author,collectionobjectid,barcode) 
    select t.highestchildnodenumber,t.nodenumber, t.name, t.author, c.collectionobjectid, p.identifier 
       from determination d left join taxon t on d.taxonid = t.taxonid 
       left join fragment f on d.fragmentid = f.fragmentid 
       left join preparation p on f.preparationid = p.preparationid   
       left join collectionobject c on f.collectionobjectid = c.collectionobjectid 
       where t.rankid > 220 and p.identifier is not null;

-- genera
insert into web_search (taxon_highestchild,taxon_nodenumber,genus,author,collectionobjectid,barcode)  
    select t.highestchildnodenumber,t.nodenumber, t.name, t.author, c.collectionobjectid, f.identifier  
    from determination d left join taxon t on d.taxonid = t.taxonid  
    left join fragment f on d.fragmentid = f.fragmentid  
    left join collectionobject c on f.collectionobjectid = c.collectionobjectid  
    where t.rankid = 180 and f.identifier is not null;

insert into web_search (taxon_highestchild,taxon_nodenumber,genus,author,collectionobjectid,barcode)  
    select t.highestchildnodenumber,t.nodenumber, t.name, t.author, c.collectionobjectid, p.identifier  
    from determination d left join taxon t on d.taxonid = t.taxonid  
    left join fragment f on d.fragmentid = f.fragmentid  
    left join collectionobject c on f.collectionobjectid = c.collectionobjectid  
    left join preparation p on f.preparationid = p.preparationid   
    where t.rankid = 180 and p.identifier;

create index idx_websearch_taxon_highestchild on web_search(taxon_highestchild);
create index idx_websearch_taxon_nodenumber on web_search(taxon_nodenumber);

-- create temporary copy of taxonomy tree in a myisam table.
drop table if exists temp_taxon; 
create table temp_taxon engine myisam as select taxonid, name, highestchildnodenumber, nodenumber, rankid, parentid from taxon;
create unique index idx_temp_taxon_taxonid on temp_taxon(taxonid);
create index temp_taxon_hc on temp_taxon(highestchildnodenumber);
create index temp_taxon_node on temp_taxon(nodenumber);
create index temp_taxon_rank on temp_taxon(rankid);

alter table temp_taxon add column family varchar(64);
-- about 6 minutes for the following
update temp_taxon set family = getHigherTaxonOfRank(140,highestchildnodenumber,nodenumber) where family is null;

alter table temp_taxon add column genus varchar(64);

-- look up the genus for taxa of rank species with a parent of rank genus
update temp_taxon left join taxon on temp_taxon.parentid = taxon.taxonid 
   set temp_taxon.genus = taxon.name 
   where temp_taxon.rankid = 220 and taxon.rankid = 180; 

--following takes hours to complete if run on the full set of records in temp_taxa.
--2 hours, 23 min if the query above looking up genera for species is run first.
update temp_taxon set genus = getHigherTaxonOfRank(180, highestchildnodenumber, nodenumber) where genus is null;

-- now update from temp_taxon into web_search (19 seconds, now that nodes are set up)
update web_search left join temp_taxon on web_search.taxon_nodenumber = temp_taxon.nodenumber 
   set web_search.family = temp_taxon.family, 
       web_search.genus = temp_taxon.genus;

-- cross join makes queries below too slow to use.
-- Queries above make this possible without a cross join
--update web_search, temp_taxon set family = temp_taxon.name 
--  where highestchildnodenumber >= taxon_highestchild 
--    and nodenumber <= taxon_nodenumber 
--    and rankid = 140 
--    and family is null;
    
-- takes 2.5 hours to run for family, more than 10 hours for genus    
--update web_search, temp_taxon set family = temp_taxon.name 
--  where taxon_nodenumber not between highestchildnodenumber and nodenumber 
--    and rankid = 140 
--    and family is null;    
    
--update web_search, temp_taxon set genus = temp_taxon.name 
--  where highestchildnodenumber >= taxon_highestchild 
--    and nodenumber <= taxon_nodenumber 
--    and rankid = 180 
--    and genus is null;    

-- Following query works, but is much too slow.
-- update web_search set family = getHigherTaxonOfRank(140,taxon_highestchild,taxon_nodenumber), 
--                      genus = getHigherTaxonOfRank(180,taxon_highestchild,taxon_nodenumber);

-- family and above
insert into web_search (taxon_highestchild,taxon_nodenumber,family,genus,author,collectionobjectid,barcode)  
   select t.highestchildnodenumber,t.nodenumber, t.name, '[None]', t.author, c.collectionobjectid, f.identifier 
       from determination d left join taxon t on d.taxonid = t.taxonid  
       left join fragment f on d.fragmentid = f.fragmentid  
       left join collectionobject c on f.collectionobjectid = c.collectionobjectid  
       where t.rankid <= 180;
       
insert into web_search (taxon_highestchild,taxon_nodenumber,family,genus,author,collectionobjectid,barcode)  
    select t.highestchildnodenumber,t.nodenumber, t.name, '[None]', t.author, c.collectionobjectid, p.identifier  
        from determination d left join taxon t on d.taxonid = t.taxonid  
        left join fragment f on d.fragmentid = f.fragmentid
        left join preparation p on f.preparationid = p.preparationid 
        left join collectionobject c on f.collectionobjectid = c.collectionobjectid  
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

create index idx_websearch_geo_highestchild on web_search(geo_highestchild);
create index idx_websearch_geo_nodenumber on web_search(geo_nodenumber);       
       
-- create temporary copy of geography tree in a myisam table.
drop table if exists temp_geography;
create table temp_geography (geographyid int primary key, name varchar(64), highestchildnodenumber int, nodenumber int, rankid int) 
    engine myisam 
    as select geographyid, name, highestchildnodenumber, nodenumber, rankid from geography;
create index temp_geography_hc on temp_geography(highestchildnodenumber);
create index temp_geography_node on temp_geography(nodenumber);
create index temp_geography_rank on temp_geography(rankid);
alter table temp_geography add column state varchar(64);
alter table temp_geography add column county varchar(64);

-- populate the country field
-- 3 min 29 sec.
update web_search, temp_geography set country = temp_geography.name 
  where geo_highestchild <= highestchildnodenumber 
    and geo_nodenumber >= nodenumber  
    and rankid = 200 
    and country is null;
    
-- 4 min 15 sec.    
update temp_geography set state = getGeographyOfRank(300,highestchildnodenumber,nodenumber);
-- 4 min 11 sec.
update temp_geography set county = getGeographyOfRank(400,highestchildnodenumber,nodenumber);
-- 24 seconds.
update web_search left join temp_geography on web_search.geo_nodenumber = temp_geography.nodenumber 
   set web_search.state = temp_geography.state, 
       web_search.county = temp_geography.county;

-- populate the state field
-- 1 hour 21 min.    
--update web_search, temp_geography set state = temp_geography.name 
--  where geo_highestchild <= highestchildnodenumber 
--    and geo_nodenumber >= nodenumber  
--    and rankid = 300 
--    and state is null;    

-- populate the county field   
-- 4 hours 13 min. 
--update web_search, temp_geography set county = temp_geography.name 
--  where geo_highestchild <= highestchildnodenumber 
--    and geo_nodenumber >= nodenumber  
--    and rankid = 400
--    and county is null;
  
-- below are way too slow, involving join with transactional tables in update query.       
--update web_search w left join collectionobject c on w.collectionobjectid = c.collectionobjectid left join collectingevent e on c.collectingeventid = e.collectingeventid left join locality l on e.localityid = l.localityid left join geography g on l.geographyid = g.geographyid 
--   set w.country = getGeographyOfRank(200,g.highestchildnodenumber,g.nodenumber);

--update web_search w left join collectionobject c on w.collectionobjectid = c.collectionobjectid left join collectingevent e on c.collectingeventid = e.collectingeventid left join locality l on e.localityid = l.localityid left join geography g on l.geographyid = g.geographyid 
--   set w.state = getGeographyOfRank(300,g.highestchildnodenumber,g.nodenumber);

--update web_search w left join collectionobject c on w.collectionobjectid = c.collectionobjectid left join collectingevent e on c.collectingeventid = e.collectingeventid left join locality l on e.localityid = l.localityid left join geography g on l.geographyid = g.geographyid 
--   set w.county = getGeographyOfRank(400,g.highestchildnodenumber,g.nodenumber);
   
-- Set type status (of specimens, not names)
create index idx_websearch_collobjid on web_search(collectionobjectid);

-- 2min 18 sec
update web_search w left join fragment f on w.collectionobjectid = f.collectionobjectid 
      left join determination d on f.fragmentid = d.fragmentid 
      set w.typestatus = d.typestatusname  
      where typestatusname is not null;

-- set date collected
-- 57 sec.
update web_search w left join collectionobject c on w.collectionobjectid = c.collectionobjectid 
    left join collectingevent ce on c.collectingeventid = ce.collectingeventid 
    set w.datecollected = getTextDate(ce.startdate, ce.startdateprecision)  
    where ce.startdate is not null;

-- set collector
-- Note: Assumes that there is only one collector record for each collecting event, and that
-- teams of people are grouped as teams of agents.  
-- 1 min 15 sec.
update web_search w left join collectionobject c on w.collectionobjectid = c.collectionobjectid 
   left join collectingevent ce on c.collectingeventid = ce.collectingeventid 
   left join collector coll on ce.collectingeventid = coll.collectingeventid 
   left join agent on coll.agentid = agent.agentid 
   set w.collector =  trim(concat(ifnull(agent.firstname,''), ' ', ifnull(agent.lastname,'')))  
   where agent.agentid is not null ;
    
-- set collectornumber  
update web_search w left join collectionobject c on w.collectionobjectid = c.collectionobjectid  
    set w.collectornumber =  c.fieldnumber 
    where c.fieldnumber is not null; 
    
-- set herbarium acronym (fragment.text1 is HUH specific where multiple herbaria are managed
-- as a single specify collection).
-- 2 min 30 sec.
update web_search w left join fragment f on w.collectionobjectid = f.collectionobjectid  set w.herbaria = f.text1 where f.text1 is not null 

-- set year collected from date collected.
update web_search set yearcollected = year(datecollected);

-- set year published (using year of publication of taxon, not of fragment or of determination)
-- 50 sec.
update web_search 
    left join fragment f on web_search.collectionobjectid = f.collectionobjectid 
    left join determination d on f.fragmentid = d.fragmentid 
    left join taxoncitation t on d.taxonid = t.taxonid 
    set web_search.yearpublished = t.text2  ;

-- Very HUH specific - link from specify to ASA image tables
-- 1 sec.
update web_search w left join IMAGE_SET_collectionobject i on w.collectionobjectid = i.collectionobjectid set w.specimenimages = true where i.collectionobjectid is not null;
-- 18 sec.
update web_search set specimenimages = false where specimenimages is null;

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
create index idx_yearcollected on web_search(yearcollected);
create index idx_yearpublished on web_search(yearpublished);



