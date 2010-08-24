-- Queries to build and populate specify 6 botany denormalized
-- tables for searching the database over the web.

-- Time benchmarks are on a laptop with a dual core 2GHz intel processor and 3GB ram.
-- The following index is needed for any non-trivial taxonomic tree:
-- create index idx_taxon_rankid on taxon(rankid); 

-- Start by building a pair of tables, temp_web_search and temp_web_quicksearch
-- When done switch these to web_search and web_quicksearch

-- Create as a stored procedure that can be run by MySQL with the 
-- Event Scheduler: 
-- CREATE EVENT event_call_populateweb
--    ON SCHEDULE
--      AT CURRENT_TIMESTAMP + INTERVAL 1 DAY
--  DO CALL populate_web_tables();

-- Comment out the next 4 lines and the last two lines to run queries directly.
DROP PROCEDURE IF EXISTS specify.populate_web_tables;
DELIMITER | 
create procedure specify.populate_web_tables ()
BEGIN

-- clean up if rerunning after incomplete run.
-- drops are better here than deletes, as they will remove the indexes as well (improving insert performance)
drop table if exists temp_web_search;
drop table if exists temp_web_quicksearch;

-- Table containing full text index on a subset of fields to 
-- use MySQL's full text index capabilities for a 'quick search'
create table if not exists temp_web_quicksearch (
  quicksearchid bigint primary key not null auto_increment,
  collectionobjectid bigint not null,
  searchable text
) ENGINE MyISAM CHARACTER SET utf8;


-- Table to hold denormalized copy of key fields for searches, particularly
-- levels from the taxonomic and geographic heirarchies.  
create table if not exists temp_web_search (
    searchid bigint primary key not null auto_increment,
    collectionobjectid bigint not null,
    family varchar (255),
    genus varchar (255),
    species varchar (255),
    infraspecific varchar (255),
    author text,
    country varchar (255),
    location text,
    host text,      -- collectionobject.text1
    substrate text, -- collectionobject.text2
    habitat text,
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
    provenance varchar(255), 
    subcollection varchar (255),
    barcode varchar (255),
    yearpublished varchar(255),
    groupfilter varchar (50),
    taxon_highestchild int,
    taxon_nodenumber int,
    geo_highestchild int,
    geo_nodenumber int
) ENGINE MyISAM CHARACTER SET utf8;

delete from temp_web_search;

-- Needed indexes to specify tables: 
-- add index to taxon(rankid) to find particular ranks
-- create index idx_taxon_rankid on taxon(rankid);
-- other indexes 
-- create index idx_agent_specialty_role on agentspecialty(role);
-- create index idx_agent_specialty_name on agentspecialty(specialtyname);
-- create index idx_taxontreedefitem_name on taxontreedefitem(name);

-- Queries that follow are designed and optimized for speed.  Fewer queries with more complex joins are 
-- possible, but much less effiecient, particularly when updates involving selects from the native InnoDB
-- tables to update rows in the temp_web_search table are involved. 

-- species rank
-- catalognumber = barcode is in fragment.identifier (primary)
-- or preparation.identifier (secondary for complex objects).
-- in HUH specify botany, collectionobject.altcatalognumber is the ASA specimen.specimenid
-- first insert the fragment barcoded names
-- 1 min 54 sec
insert into temp_web_search 
  (taxon_highestchild,taxon_nodenumber,species,author,collectionobjectid,barcode) 
  select t.highestchildnodenumber,t.nodenumber, t.name, t.author, 
         c.collectionobjectid, f.identifier
         from 
         determination d left join taxon t on d.taxonid = t.taxonid 
         left join fragment f on d.fragmentid = f.fragmentid 
         left join collectionobject c on f.collectionobjectid = c.collectionobjectid
          left join taxontreedefitem td on t.rankid = td.rankid 
         where td.name = 'Species' and f.identifier is not null;
-- repeat for preparation barcoded names
-- 1 min 28 sec
insert into temp_web_search 
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
         
-- insert into temp_web_search (family,genus,species,infraspecific,author,collectionobjectid,barcode) select getHigherTaxonOfRank(140,t.highestchildnodenumber,t.nodenumber) as family, getHigherTaxonOfRank(180,t.highestchildnodenumber,t.nodenumber) as genus, getParentRank(220,t.highestchildnodenumber,t.nodenumber), t.name, t.author, c.collectionobjectid, c.altcatalognumber from determination d left join taxon t on d.taxonid = t.taxonid left join fragment f on d.fragmentid = f.fragmentid left join collectionobject c on f.collectionobjectid = c.collectionobjectid where t.rankid > 220;
-- below species rank
-- 13 sec
insert into temp_web_search (taxon_highestchild,taxon_nodenumber,infraspecific,author,collectionobjectid,barcode) 
   select t.highestchildnodenumber,t.nodenumber, t.name, t.author, c.collectionobjectid, f.identifier
     from determination d left join taxon t on d.taxonid = t.taxonid 
     left join fragment f on d.fragmentid = f.fragmentid 
     left join collectionobject c on f.collectionobjectid = c.collectionobjectid 
     where t.rankid > 220 and f.identifier is not null;
-- 10 sec
insert into temp_web_search (taxon_highestchild,taxon_nodenumber,infraspecific,author,collectionobjectid,barcode) 
    select t.highestchildnodenumber,t.nodenumber, t.name, t.author, c.collectionobjectid, p.identifier 
       from determination d left join taxon t on d.taxonid = t.taxonid 
       left join fragment f on d.fragmentid = f.fragmentid 
       left join preparation p on f.preparationid = p.preparationid   
       left join collectionobject c on f.collectionobjectid = c.collectionobjectid 
       where t.rankid > 220 and p.identifier is not null;

-- genera
-- 5 sec
insert into temp_web_search (taxon_highestchild,taxon_nodenumber,genus,author,collectionobjectid,barcode)  
    select t.highestchildnodenumber,t.nodenumber, t.name, t.author, c.collectionobjectid, f.identifier  
    from determination d left join taxon t on d.taxonid = t.taxonid  
    left join fragment f on d.fragmentid = f.fragmentid  
    left join collectionobject c on f.collectionobjectid = c.collectionobjectid  
    where t.rankid = 180 and f.identifier is not null;

-- 4 sec    
insert into temp_web_search (taxon_highestchild,taxon_nodenumber,genus,author,collectionobjectid,barcode)  
    select t.highestchildnodenumber,t.nodenumber, t.name, t.author, c.collectionobjectid, p.identifier  
    from determination d left join taxon t on d.taxonid = t.taxonid  
    left join fragment f on d.fragmentid = f.fragmentid  
    left join collectionobject c on f.collectionobjectid = c.collectionobjectid  
    left join preparation p on f.preparationid = p.preparationid   
    where t.rankid = 180 and p.identifier;

-- 11 sec    
create index idx_websearch_taxon_highestchild on temp_web_search(taxon_highestchild);
-- 15 sec
create index idx_websearch_taxon_nodenumber on temp_web_search(taxon_nodenumber);

-- create temporary copy of taxonomy tree in a myisam table.
drop table if exists temp_taxon;
-- 2 sec
create table temp_taxon engine myisam as select taxonid, name, highestchildnodenumber, nodenumber, rankid, parentid from taxon;
-- about 3-5 sec each 
create unique index idx_temp_taxon_taxonid on temp_taxon(taxonid);
create index temp_taxon_hc on temp_taxon(highestchildnodenumber);
create index temp_taxon_node on temp_taxon(nodenumber);
create index temp_taxon_rank on temp_taxon(rankid);
-- 5 sec
alter table temp_taxon add column family varchar(64);

-- lookup family for genera
-- 1 sec
update temp_taxon left join taxon on temp_taxon.parentid = taxon.taxonid 
   set temp_taxon.family = taxon.name 
   where temp_taxon.rankid = 180 and taxon.rankid = 140; 

-- lookup family for species
-- 2 sec. 
update temp_taxon left join taxon p1 on temp_taxon.parentid = p1.taxonid
   left join taxon p2 on p1.parentid = p2.taxonid
   set temp_taxon.family = p2.name 
   where temp_taxon.rankid = 220 and p2.rankid = 140; 

-- about 6 minutes for the following, if family for species and genera aren't looked up first
-- 57 sec.
update temp_taxon set family = getHigherTaxonOfRank(140,highestchildnodenumber,nodenumber) where family is null;

-- 2 sec
alter table temp_taxon add column genus varchar(64);

-- populate the genus for taxa of rank genus
-- 2 sec
update temp_taxon left join taxon on temp_taxon.taxonid = taxon.taxonid 
   set temp_taxon.genus = taxon.name 
   where temp_taxon.rankid = 180 and taxon.rankid = 180; 
   
-- repeat for any ranks below genus that occur with non-trivial frequency in the data
   
-- look up the genus for taxa of rank species with a parent of rank genus
-- 5 sec
update temp_taxon left join taxon on temp_taxon.parentid = taxon.taxonid 
   set temp_taxon.genus = taxon.name 
   where temp_taxon.rankid = 220 and taxon.rankid = 180; 
-- a few seconds each
-- repeat for subspecific taxa of various sorts
update temp_taxon left join taxon p1 on temp_taxon.parentid = p1.taxonid
   left join taxon p2 on p1.parentid = p2.taxonid
   set temp_taxon.genus = p2.name 
   where temp_taxon.rankid = 230 and p2.rankid = 180; 
update temp_taxon left join taxon p1 on temp_taxon.parentid = p1.taxonid
   left join taxon p2 on p1.parentid = p2.taxonid
   set temp_taxon.genus = p2.name 
   where temp_taxon.rankid = 240 and p2.rankid = 180; 
update temp_taxon left join taxon p1 on temp_taxon.parentid = p1.taxonid
   left join taxon p2 on p1.parentid = p2.taxonid
   set temp_taxon.genus = p2.name 
   where temp_taxon.rankid = 250 and p2.rankid = 180; 
update temp_taxon left join taxon p1 on temp_taxon.parentid = p1.taxonid
   left join taxon p2 on p1.parentid = p2.taxonid
   set temp_taxon.genus = p2.name 
   where temp_taxon.rankid = 260 and p2.rankid = 180; 

-- fill in the remainder (use the above queries to catch most cases) 
-- following takes hours to complete if run on the full set of records in temp_taxa.
-- 2 hours, 23 min if the query above looking up genera for species is run first.
-- update temp_taxon set genus = getHigherTaxonOfRank(180, highestchildnodenumber, nodenumber) 
--   where genus is null;
-- 1.2 sec on just the few remaining rows 
update temp_taxon set genus = getHigherTaxonOfRank(180, highestchildnodenumber, nodenumber) 
   where genus is null and temp_taxon.rankid > 260;

-- now update from temp_taxon into temp_web_search (19 seconds, now that nodes are set up)
-- 19 sec
update temp_web_search left join temp_taxon on temp_web_search.taxon_nodenumber = temp_taxon.nodenumber 
   set temp_web_search.family = temp_taxon.family, 
       temp_web_search.genus = temp_taxon.genus;


-- family and above
-- 3 sec
insert into temp_web_search (taxon_highestchild,taxon_nodenumber,family,genus,author,collectionobjectid,barcode)  
   select t.highestchildnodenumber,t.nodenumber, t.name, '[None]', t.author, c.collectionobjectid, f.identifier 
       from determination d left join taxon t on d.taxonid = t.taxonid  
       left join fragment f on d.fragmentid = f.fragmentid  
       left join collectionobject c on f.collectionobjectid = c.collectionobjectid  
       where t.rankid < 180;
-- 2 sec       
insert into temp_web_search (taxon_highestchild,taxon_nodenumber,family,genus,author,collectionobjectid,barcode)  
    select t.highestchildnodenumber,t.nodenumber, t.name, '[None]', t.author, c.collectionobjectid, p.identifier  
        from determination d left join taxon t on d.taxonid = t.taxonid  
        left join fragment f on d.fragmentid = f.fragmentid
        left join preparation p on f.preparationid = p.preparationid 
        left join collectionobject c on f.collectionobjectid = c.collectionobjectid  
        where t.rankid < 180;        
        
-- Denormalize geography 
-- locality, append geo name if geo is not a country, state, or county.
-- 44 sec
update temp_web_search 
   left join  collectionobject c on temp_web_search.collectionobjectid = c.collectionobjectid 
   left join collectingevent e on c.collectingeventid = e.collectingeventid 
   left join locality l on e.localityid = l.localityid 
   left join geography g on l.geographyid = g.geographyid
   set temp_web_search.location = 
       concat(coalesce(g.name,''), ': ', coalesce(l.localityname,''), ' ', coalesce(e.verbatimlocality,'')), 
       temp_web_search.geo_highestchild = g.highestchildnodenumber, 
       temp_web_search.geo_nodenumber = g.nodenumber
   where g.rankid <> 200 and g.rankid <> 300 and g.rankid <> 400;
-- locality, don't append geo name for country, state or county, these will be represnted separately.
-- 52 sec        
update temp_web_search 
   left join  collectionobject c on temp_web_search.collectionobjectid = c.collectionobjectid 
   left join collectingevent e on c.collectingeventid = e.collectingeventid 
   left join locality l on e.localityid = l.localityid 
   left join geography g on l.geographyid = g.geographyid
   set temp_web_search.location = 
       concat(coalesce(l.localityname,''), ' ', coalesce(e.verbatimlocality,'')), 
       temp_web_search.geo_highestchild = g.highestchildnodenumber, 
       temp_web_search.geo_nodenumber = g.nodenumber
   where g.rankid = 200 or g.rankid = 300 or g.rankid = 400;
       
-- 9 sec   
create index idx_websearch_geo_highestchild on temp_web_search(geo_highestchild);
-- 8 sec
create index idx_websearch_geo_nodenumber on temp_web_search(geo_nodenumber);       
       
-- create temporary copy of geography tree in a myisam table.
drop table if exists temp_geography;
-- 0.24 sec
create table temp_geography (geographyid int primary key, name varchar(64), highestchildnodenumber int, nodenumber int, rankid int) 
    engine myisam 
    as select geographyid, name, highestchildnodenumber, nodenumber, rankid from geography;
-- about 0.1 sec each    
create index temp_geography_hc on temp_geography(highestchildnodenumber);
create index temp_geography_node on temp_geography(nodenumber);
create index temp_geography_rank on temp_geography(rankid);
alter table temp_geography add column state varchar(64);
alter table temp_geography add column county varchar(64);

-- populate the country field
-- 3 min 29 sec.
update temp_web_search, temp_geography set country = temp_geography.name 
  where geo_highestchild <= highestchildnodenumber 
    and geo_nodenumber >= nodenumber  
    and rankid = 200 
    and country is null;
    
-- 4 min 15 sec.    
update temp_geography set state = getGeographyOfRank(300,highestchildnodenumber,nodenumber);
-- 4 min 11 sec.
update temp_geography set county = getGeographyOfRank(400,highestchildnodenumber,nodenumber);
-- 24 seconds.
update temp_web_search left join temp_geography on temp_web_search.geo_nodenumber = temp_geography.nodenumber 
   set temp_web_search.state = temp_geography.state, 
       temp_web_search.county = temp_geography.county;

   
-- Set type status (of specimens, not names)
create index idx_websearch_collobjid on temp_web_search(collectionobjectid);

-- 2min 18 sec
update temp_web_search w left join fragment f on w.collectionobjectid = f.collectionobjectid 
      left join determination d on f.fragmentid = d.fragmentid 
      set w.typestatus = d.typestatusname  
      where typestatusname is not null;

-- set date collected
-- 57 sec.
update temp_web_search w left join collectionobject c on w.collectionobjectid = c.collectionobjectid 
    left join collectingevent ce on c.collectingeventid = ce.collectingeventid 
    set w.datecollected = getTextDate(ce.startdate, ce.startdateprecision)  
    where ce.startdate is not null;

-- set collector
-- Note: Assumes that there is only one collector record for each collecting event, and that
-- teams of people are grouped as teams of agents.  
-- TODO: Add new collector.etal field to concatenation.
-- 
-- AgentVariant.vartype = 4 is label name/collector name
    
-- 1 min 15 sec.
update temp_web_search w left join collectionobject c on w.collectionobjectid = c.collectionobjectid 
   left join collectingevent ce on c.collectingeventid = ce.collectingeventid 
   left join collector coll on ce.collectingeventid = coll.collectingeventid 
   left join agentvariant on coll.agentid = agentvariant.agentid 
   set w.collector =  trim(concat(ifnull(agentvariant.name,''), ' ', ifnull(coll.etal,'')))  
   where agentvariant.agentid is not null and agentvariant.vartype = 4 ;
    
-- set collectornumber  
-- 50 sec
update temp_web_search w left join collectionobject c on w.collectionobjectid = c.collectionobjectid  
    set w.collectornumber =  c.fieldnumber 
    where c.fieldnumber is not null; 
    
-- set herbarium acronym (fragment.text1 is HUH specific where multiple herbaria are managed
-- as a single specify collection).
-- 2 min 30 sec.
update temp_web_search w left join fragment f on w.collectionobjectid = f.collectionobjectid 
  set w.herbaria = f.text1 
  where f.text1 is not null ;

-- set provenance  
-- 3.3. sec.
update temp_web_search w left join fragment f on w.collectionobjectid = f.collectionobjectid 
  set w.provenance = f.Provenance 
  where f.Provenance is not null ;  
  
  
-- set year collected from date collected.
-- 4 sec.
update temp_web_search set yearcollected = year(datecollected);

-- set year published (using year of publication of taxon, not of fragment or of determination)
-- 50 sec.
update temp_web_search 
    left join fragment f on temp_web_search.collectionobjectid = f.collectionobjectid 
    left join determination d on f.fragmentid = d.fragmentid 
    left join taxoncitation t on d.taxonid = t.taxonid 
    set temp_web_search.yearpublished = t.text2  ;
    
-- set host (from collectionobject.text1)
-- 20 sec
update temp_web_search left join collectionobject c on temp_web_search.collectionobjectid = c.collectionobjectid
    set temp_web_search.host = c.text1;
    
-- set substrate (from collectionobject.text2)
-- 15 sec. 
update temp_web_search left join collectionobject c on temp_web_search.collectionobjectid = c.collectionobjectid
    set temp_web_search.substrate = c.text2;
    
-- set habitat (from collectingevent.remarks) 
-- 36 sec.
update temp_web_search left join collectionobject c on temp_web_search.collectionobjectid = c.collectionobjectid
    left join collectingevent ce on c.collectingeventid = ce.collectingeventid
    set temp_web_search.habitat = ce.remarks;
    
-- Very HUH specific - link from specify to ASA image tables
-- 1 sec.
update temp_web_search w left join IMAGE_SET_collectionobject i on w.collectionobjectid = i.collectionobjectid set w.specimenimages = true where i.collectionobjectid is not null;
-- 18 sec.
update temp_web_search set specimenimages = false where specimenimages is null;

create index idx_websearch_family on temp_web_search(family);
create index idx_websearch_genus on temp_web_search(genus);
create index idx_websearch_species on temp_web_search(species);
create index idx_websearch_infraspecific on temp_web_search(infraspecific);
create index idx_websearch_author on temp_web_search(author(50));
create index idx_websearch_country on temp_web_search(country);
create index idx_websearch_location on temp_web_search(location(100));
create index idx_websearch_host on temp_web_search(host(100));
create index idx_websearch_substrate on temp_web_search(substrate(100));
create index idx_websearch_habitat on temp_web_search(habitat(100));
create index idx_websearch_state on temp_web_search(state);
create index idx_websearch_county on temp_web_search(county);
create index idx_websearch_typestatus on temp_web_search(typestatus);
create index idx_websearch_collector on temp_web_search(collector(50));
create index idx_websearch_collectornumber on temp_web_search(collectornumber);
create index idx_websearch_herbaria on temp_web_search(herbaria);
create index idx_websearch_provenance on temp_web_search(provenance);
create index idx_websearch_barcode on temp_web_search(barcode);
create index idx_websearch_datecollected on temp_web_search(datecollected);
create index idx_yearcollected on temp_web_search(yearcollected);
create index idx_yearpublished on temp_web_search(yearpublished);

delete from temp_web_quicksearch;

-- Not needed as the temp tables are being built and removed.
-- drop index i_temp_web_quicksearch on temp_web_quicksearch;

-- 4 sec
insert into temp_web_quicksearch (collectionobjectid, searchable) (
   select collectionobjectid, 
         concat_ws(" ",
            family,genus,species,infraspecific,author,yearpublished,typestatus,provenance,
            country,state,county,location,host,substrate,habitat,datecollected,collector,collectornumber,barcode) 
         from temp_web_search
   ); 

-- 1 min 4 sec   
create fulltext index i_temp_web_quicksearch on temp_web_quicksearch(searchable);

-- switch out the web_search tables for the newly build temp_web_search tables

rename table web_search to old_web_search, temp_web_search to web_search, web_quicksearch to old_web_quicksearch, temp_web_quicksearch to web_quicksearch;

-- Clean up.  Remove the previous copies of the tables. 
drop table old_web_search;
drop table old_web_quicksearch;

-- Some things that don't work.

-- Trying to do a nested join to populate a search table with a denormalized set of related data.
-- insert into temp_web_quicksearch (collectionobjectid, searchable) (
--   select a.collectionobjectid, concat(geography.name, ' ', gloc.name, ' ', a.fullname, ' ', a.catalognumber) 
--   from geography, 
--       (select distinct taxon.fullname, locality.geographyid geoid, collectionobject.altcatalognumber as catalognumber, collectionobject.collectionobjectid 
--        from collectionobject 
--            left join fragment on collectionobject.collectionobjectid = fragment.collectionobjectid 
--            left join determination on fragment.fragmentid = determination.fragmentid 
--            left join taxon on determination.taxonid = taxon.taxonid 
--            left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid 
--            left join locality on collectingevent.localityid = locality.localityid 
--        ) a
--   left join geography gloc on a.geoid = gloc.geographyid 
--   where geography.rankid = 200 
--     and geography.highestchildnodenumber >= a.geoid 
--     and geography.nodenumber <= a.geoid);

-- populate the state field
-- 1 hour 21 min.    
-- update temp_web_search, temp_geography set state = temp_geography.name 
--  where geo_highestchild <= highestchildnodenumber 
--    and geo_nodenumber >= nodenumber  
--    and rankid = 300 
--    and state is null;    

-- populate the county field   
-- 4 hours 13 min. 
-- update temp_web_search, temp_geography set county = temp_geography.name 
--  where geo_highestchild <= highestchildnodenumber 
--    and geo_nodenumber >= nodenumber  
--    and rankid = 400
--    and county is null;
  
-- below are way too slow, involving join with transactional tables in update query.       
-- update temp_web_search w left join collectionobject c on w.collectionobjectid = c.collectionobjectid left join collectingevent e on c.collectingeventid = e.collectingeventid left join locality l on e.localityid = l.localityid left join geography g on l.geographyid = g.geographyid 
--   set w.country = getGeographyOfRank(200,g.highestchildnodenumber,g.nodenumber);

-- update temp_web_search w left join collectionobject c on w.collectionobjectid = c.collectionobjectid left join collectingevent e on c.collectingeventid = e.collectingeventid left join locality l on e.localityid = l.localityid left join geography g on l.geographyid = g.geographyid 
--   set w.state = getGeographyOfRank(300,g.highestchildnodenumber,g.nodenumber);

-- update temp_web_search w left join collectionobject c on w.collectionobjectid = c.collectionobjectid left join collectingevent e on c.collectingeventid = e.collectingeventid left join locality l on e.localityid = l.localityid left join geography g on l.geographyid = g.geographyid 
--   set w.county = getGeographyOfRank(400,g.highestchildnodenumber,g.nodenumber);

-- cross join on InnoDB tables makes queries below too slow to use.
-- Queries above make this possible without a cross join
-- update temp_web_search, temp_taxon set family = temp_taxon.name 
--  where highestchildnodenumber >= taxon_highestchild 
--    and nodenumber <= taxon_nodenumber 
--    and rankid = 140 
--    and family is null;
    
-- takes 2.5 hours to run for family, more than 10 hours for genus    
-- update temp_web_search, temp_taxon set family = temp_taxon.name 
--  where taxon_nodenumber not between highestchildnodenumber and nodenumber 
--    and rankid = 140 
--    and family is null;    
    
-- update temp_web_search, temp_taxon set genus = temp_taxon.name 
--  where highestchildnodenumber >= taxon_highestchild 
--    and nodenumber <= taxon_nodenumber 
--    and rankid = 180 
--    and genus is null;    

-- Using a function to look up nodes in the path to root in the trees.
-- Following query works, but is much too slow.
-- update temp_web_search set family = getHigherTaxonOfRank(140,taxon_highestchild,taxon_nodenumber), 
--                      genus = getHigherTaxonOfRank(180,taxon_highestchild,taxon_nodenumber);

-- Comment out the leading 4 lines and the next two lines to run queries directly.
END| 
DELIMITER ;