-- Queries to build and populate specify 6 botany denormalized
-- tables for searching the database over the web.

-- Requires grant execute after firing against database.
-- Note: This grant must specify servername, not localhost (but may need both...).
-- grant execute on procedure specify.populate_web_tables to 'specify_web_adm'@'kiki.huh.harvard.edu';
-- grant execute on procedure specify.populate_web_tables to 'specify_web_adm'@'localhost';

-- Time benchmarks are on a laptop with a dual core 2GHz intel processor and 3GB ram.
-- The following index is needed for any non-trivial taxonomic tree:
-- create index idx_taxon_rankid on taxon(rankid);

-- Start by building a pair of tables, temp_web_search and temp_web_quicksearch
-- When done switch these to web_search and web_quicksearch

-- Create as a stored procedure that can be run by MySQL with the
-- Event Scheduler (Needs MySQL 5.1.6+):
-- CREATE EVENT event_call_populateweb
--    ON SCHEDULE
--      AT CURRENT_TIMESTAMP + INTERVAL 1 DAY
--  DO CALL populate_web_tables();

-- Copyright © 2010 President and Fellows of Harvard College
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 2 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see <http://www.gnu.org/licenses/>.
--
-- Author: Paul J. Morris  bdim@oeb.harvard.edu

-- Comment out the next 4 lines and the last two lines to run queries directly.
DROP PROCEDURE IF EXISTS specify.populate_web_tables;
DELIMITER |
create  DEFINER=`root`@`localhost` procedure specify.populate_web_tables ()
BEGIN

-- clean up if rerunning after incomplete run.
-- drops are better here than deletes, as they will remove the indexes as well (improving insert performance)
drop table if exists temp_web_search;
drop table if exists temp_web_quicksearch;
drop table if exists temp_dwc_search;
drop table if exists temp_dwc_identification_history;


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
    geo_nodenumber int,
    sensitive_flag int default 0
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
create table temp_taxon engine myisam as select taxonid, name, highestchildnodenumber, nodenumber, rankid, parentid, citesstatus, groupnumber from taxon;
-- about 3-5 sec each
create unique index idx_temp_taxon_taxonid on temp_taxon(taxonid);
create index temp_taxon_hc on temp_taxon(highestchildnodenumber);
create index temp_taxon_node on temp_taxon(nodenumber);
create index temp_taxon_rank on temp_taxon(rankid);
create index temp_taxon_sens on temp_taxon(citesstatus);
create index temp_taxon_grp  on temp_taxon(groupnumber);
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


alter table temp_taxon add column species varchar(64);
-- Fill in species epithet for infraspecific names
-- About 1 sec total for these.
update temp_taxon left join taxon p1 on temp_taxon.parentid = p1.taxonid
   set temp_taxon.species = p1.name
   where temp_taxon.rankid = 230 and p1.rankid = 220;
update temp_taxon left join taxon p1 on temp_taxon.parentid = p1.taxonid
   set temp_taxon.species = p1.name
   where temp_taxon.rankid = 240 and p1.rankid = 220;
update temp_taxon left join taxon p1 on temp_taxon.parentid = p1.taxonid
   set temp_taxon.species = p1.name
   where temp_taxon.rankid = 250 and p1.rankid = 220;
update temp_taxon left join taxon p1 on temp_taxon.parentid = p1.taxonid
   set temp_taxon.species = p1.name
   where temp_taxon.rankid = 260 and p1.rankid = 220;

-- fill in the remainder (use the above queries to catch most cases)
-- following takes hours to complete if run on the full set of records in temp_taxa.
-- 2 hours, 23 min if the query above looking up genera for species is run first.
-- update temp_taxon set genus = getHigherTaxonOfRank(180, highestchildnodenumber, nodenumber)
--   where genus is null;
-- 1.2 sec on just the few remaining rows
update temp_taxon set genus = getHigherTaxonOfRank(180, highestchildnodenumber, nodenumber)
   where genus is null and temp_taxon.rankid > 260;
-- 5 sec.
update temp_taxon set species = getHigherTaxonOfRank(220, highestchildnodenumber, nodenumber)
   where species is null and temp_taxon.rankid > 260;

-- now update from temp_taxon into temp_web_search (19 seconds, now that nodes are set up)
-- 19 sec
update temp_web_search left join temp_taxon on temp_web_search.taxon_nodenumber = temp_taxon.nodenumber
   set temp_web_search.family = temp_taxon.family,
       temp_web_search.genus = temp_taxon.genus;

update temp_web_search left join temp_taxon on temp_web_search.taxon_nodenumber = temp_taxon.nodenumber
   set temp_web_search.species = temp_taxon.species
 where temp_taxon.species is not null and temp_taxon.rankid > 220;

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

--  now set sensitive flag
update temp_web_search left join temp_taxon on temp_web_search.taxon_nodenumber = temp_taxon.nodenumber
   set temp_web_search.sensitive_flag = 1
 where temp_taxon.citesstatus != 'None';

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
create table temp_geography (geographyid int primary key, name varchar(64), geographycode varchar(32), highestchildnodenumber int, nodenumber int, rankid int)
    engine myisam
    as select geographyid, name, geographycode, highestchildnodenumber, nodenumber, rankid from geography;
-- about 0.1 sec each
create index temp_geography_hc on temp_geography(highestchildnodenumber);
create index temp_geography_node on temp_geography(nodenumber);
create index temp_geography_rank on temp_geography(rankid);
alter table temp_geography add column state varchar(64);
alter table temp_geography add column county varchar(64);
alter table temp_geography add column continent varchar(64);
alter table temp_geography add column region varchar(64);
alter table temp_geography add column archipelago varchar(64);
alter table temp_geography add column country varchar(64);
alter table temp_geography add column land varchar(64);
alter table temp_geography add column territory varchar(64);
alter table temp_geography add column subcontinentislands varchar(64);
alter table temp_geography add column continentsubregion varchar(64);
alter table temp_geography add column countrysubregion varchar(64);
alter table temp_geography add column straights varchar(64);
alter table temp_geography add column subcountryislands varchar(64);


-- populate the country field
-- 3 min 29 sec.
update temp_web_search, temp_geography set temp_web_search.country = temp_geography.name
  where geo_highestchild <= highestchildnodenumber
    and geo_nodenumber >= nodenumber
    and rankid = 200
    and temp_web_search.country is null;

-- 4 min 15 sec.
update temp_geography set state = getGeographyOfRank(300,highestchildnodenumber,nodenumber);
-- 4 min 11 sec.
update temp_geography set county = getGeographyOfRank(400,highestchildnodenumber,nodenumber);
-- other tree levels used for dwc_search
-- about 5 minutes each.  Not faster if used with where clause.
update temp_geography set continent = getGeographyOfRank(100,highestchildnodenumber,nodenumber);
update temp_geography set country = getGeographyOfRank(200,highestchildnodenumber,nodenumber);
update temp_geography set continentsubregion = getGeographyOfRank(250,highestchildnodenumber,nodenumber);
update temp_geography set countrysubregion = getGeographyOfRank(260,highestchildnodenumber,nodenumber);
-- Apply limits to less frequently used levels, bit faster for region.
-- 3 min
update temp_geography set region = getGeographyOfRank(150,highestchildnodenumber,nodenumber)
  where nodenumber >= (select min(nodenumber) from geography where rankid = 150) and highestchildnodenumber <= (select max(highestchildnodenumber) from geography where rankid = 150);
-- much faster for very rarely used levels where all enclosed nodes are in small parts of tree.
-- < 1 second each
update temp_geography set archipelago = getGeographyOfRank(160,highestchildnodenumber,nodenumber)
  where nodenumber >= (select min(nodenumber) from geography where rankid = 160) and highestchildnodenumber <= (select max(highestchildnodenumber) from geography where rankid = 160);
update temp_geography set land = getGeographyOfRank(210,highestchildnodenumber,nodenumber)
  where nodenumber >= (select min(nodenumber) from geography where rankid = 210) and highestchildnodenumber <= (select max(highestchildnodenumber) from geography where rankid = 210);
update temp_geography set territory = getGeographyOfRank(220,highestchildnodenumber,nodenumber)
  where nodenumber >= (select min(nodenumber) from geography where rankid = 220) and highestchildnodenumber <= (select max(highestchildnodenumber) from geography where rankid = 220);
update temp_geography set subcontinentislands = getGeographyOfRank(230,highestchildnodenumber,nodenumber)
  where nodenumber >= (select min(nodenumber) from geography where rankid = 230) and highestchildnodenumber <= (select max(highestchildnodenumber) from geography where rankid = 230);
update temp_geography set straights = getGeographyOfRank(270,highestchildnodenumber,nodenumber)
  where nodenumber >= (select min(nodenumber) from geography where rankid = 270) and highestchildnodenumber <= (select max(highestchildnodenumber) from geography where rankid = 270);
-- 1 min
update temp_geography set subcountryislands = getGeographyOfRank(280,highestchildnodenumber,nodenumber)
  where nodenumber >= (select min(nodenumber) from geography where rankid = 280) and highestchildnodenumber <= (select max(highestchildnodenumber) from geography where rankid = 280);

-- Alternatives much slower, e.g. cross join about 5 minutes for just a select.
-- select p.name, p.nodenumber, p.highestchildnodenumber,c.name from geography p, geography c where p.rankid = 160 and p.nodenumber <= c.nodenumber and p.highestchildnodenumber >= c.nodenumber;


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
    set w.datecollected = getTextDate(ce.startdate, ce.startdateprecision),
    w.yearcollected = year(ce.startdate)
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
-- 2 min
update temp_web_search w left join collectionobject c on w.collectionobjectid = c.collectionobjectid
    left join collectingevent e on c.collectingeventid = e.collectingeventid
    set w.collectornumber =  e.stationfieldnumber
    where e.stationfieldnumber is not null;

-- TODO: set collectornumber using series id

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
-- Replaced above, year doesn't handle arbitrary precision ISO dates.
-- update temp_web_search set yearcollected = year(datecollected);

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

-- Redact other sensitive information
-- esastatus = 'controlled' = on DEA controlled substance list.
delete from temp_web_search where taxon_nodenumber in (select nodenumber from taxon where esastatus is not null);

create table if not exists web_search (id int);
create table if not exists web_quicksearch (id int);
drop table if exists old_web_search;
drop table if exists old_web_quicksearch;
-- switch out the web_search tables for the newly build temp_web_search tables
rename table web_search to old_web_search, temp_web_search to web_search, web_quicksearch to old_web_quicksearch, temp_web_quicksearch to web_quicksearch;

-- Clean up.  Remove the previous copies of the tables.
drop table old_web_search;
drop table old_web_quicksearch;

-- Build darwin core flat file

-- Provide fields suitable for mapping both TDWG DarwinCore with AppleCore guidance and DarwinCoreV2.
create table if not exists temp_dwc_search (
  dwc_searchid bigint not null primary key auto_increment,
  collectionobjectid bigint not null,
  institution varchar(25) default 'Harvard University',
  collectioncode varchar(5),
  collectionid varchar(50),
  catalognumber varchar(32) not null,
  catalognumbernumeric int,
  dc_type varchar(255) default 'http://purl.org/dc/dcmitype/PhysicalObject',
  basisofrecord varchar(255) default 'PreservedSpecimen',
  collectornumber varchar(50),
  samplingprotocol text,
  collector text,
  sex varchar(32),
  reproductiveStatus varchar(32),
  preparations text,
  verbatimdate varchar(50),
  eventdate varchar(50),
  year int,
  month int,
  day int,
  startdayofyear int,
  enddayofyear int,
  startdatecollected date,
  enddatecollected date,
  habitat text,
  highergeography text,
  continent varchar(64),
  country varchar(64),
  countrycode varchar(32),
  stateprovince varchar(64),
  islandgroup varchar(128),
  county varchar(64),
  island varchar(64),
  municipality varchar(64),
  locality text,
  localityremarks text,
  minimumelevationmeters double,
  maximumelevationmeters double,
  verbatimelevation varchar(50),
  decimallatitude decimal(12,10),
  decimallongitude decimal(13,10),
  coordinateuncertaintyinmeters int,
  geodeticdatum varchar(50),
  identifiedby text,
  dateidentified varchar(50),
  identificationqualifier varchar(50),
  identificationremarks text,
  identificationreferences text,
  typestatus varchar(50),
  scientificname varchar(255),
  scientificnameauthorship varchar(128),
  specificepithet varchar(255),
  infraspecificepithet varchar(255),
  genus  varchar(255),
  family varchar(255),
  informationwitheld varchar(255),
  datageneralizations varchar(255),
  othercatalognumbers text,
  occurenceremarks text,
  fragmentguid char(100) not null unique,
  timestamplastupdated datetime,
  hasimage int default 0,
  imagedescription text,
  imagecreatedate datetime,
  imagecreator varchar(255),
  imageuri varchar(255),
  occurenceuri varchar(255),
  taxonomicgroup varchar(255),
  dynamicproperties text,
  temp_identifier varchar(32) not null,
  temp_prepmethod varchar(32),
  temp_startdate date,
  temp_enddate date,
  temp_startdateprecision int,
  temp_enddateprecision int,
  temp_geographyid int,
  temp_determinationid bigint,
  temp_fragmentid bigint,
  temp_projectid int,
  temp_projectname varchar(255),
  unredacted_locality text,
  unredacted_decimallatitude decimal(12,10),
  unredacted_decimallongitude decimal(13,10),
  old_fragmentguid char(100)
) ENGINE MyISAM CHARACTER SET utf8;

delete from temp_dwc_search;

-- ignore will cause duplicate guids to be skipped.
-- text1 contains herbarium acronym.
-- 40 sec.
insert ignore into temp_dwc_search (collectionobjectid, collectioncode, catalognumber, catalognumbernumeric, temp_identifier, temp_prepmethod, fragmentguid, timestamplastupdated, temp_fragmentid, occurenceremarks, occurenceuri) select distinct collectionobjectid, text1, concat('barcode-', identifier), identifier, identifier, prepmethod, uuid, ifnull(timestampmodified,timestampcreated), fragment.fragmentid, concat(fragment.description, " | ", fragment.remarks), concat('http://data.huh.harvard.edu/',uuid) from fragment left join guids on fragment.fragmentid = guids.primarykey where identifier is not null and guids.tablename = 'fragment';

-- add barcoded preparations
insert ignore into temp_dwc_search (collectionobjectid, collectioncode, catalognumber, catalognumbernumeric, temp_identifier, temp_prepmethod, fragmentguid, timestamplastupdated, temp_fragmentid, occurenceremarks, occurenceuri) select distinct collectionobjectid, fragment.text1, concat('barcode-', p.identifier), p.identifier, p.identifier, prepmethod, uuid, ifnull(fragment.timestampmodified,fragment.timestampcreated), fragment.fragmentid, concat(fragment.description, " | ", fragment.remarks), concat('http://data.huh.harvard.edu/',uuid) from fragment left join guids on fragment.fragmentid = guids.primarykey left join preparation p on fragment.preparationid = p.preparationid where p.identifier is not null and guids.tablename = 'fragment';

-- update modified timestamp based on changes to relevant tables
update temp_dwc_search
  left join fragment
    on temp_dwc_search.temp_fragmentid = fragment.fragmentid
  left join determination
    on fragment.fragmentid = determination.fragmentid
  left join collectionobject
    on fragment.collectionobjectid = collectionobject.collectionobjectid
  left join collectingevent
    on collectionobject.collectingeventid = collectingevent.collectingeventid
  left join locality
    on collectingevent.localityid = locality.localityid
  set temp_dwc_search.timestamplastupdated = GREATEST(COALESCE(fragment.timestampcreated, '1000-01-01'), COALESCE(fragment.timestampmodified, '1000-01-01'), COALESCE(determination.timestampcreated, '1000-01-01'), COALESCE(determination.timestampmodified, '1000-01-01'), COALESCE(collectionobject.timestampcreated, '1000-01-01'), COALESCE(collectionobject.timestampmodified, '1000-01-01'), COALESCE(collectingevent.timestampcreated, '1000-01-01'), COALESCE(collectingevent.timestampmodified, '1000-01-01'), COALESCE(locality.timestampcreated, '1000-01-01'), COALESCE(locality.timestampmodified, '1000-01-01'));

-- make the fragment guid resolvable
-- 10 sec
update temp_dwc_search set old_fragmentguid = concat('http://purl.oclc.org/net/edu.harvard.huh/guid/uuid/',fragmentguid);

-- Index on the catalog number to speed later operations.
-- 28 sec
create index temp_dwc_searchcatnum on temp_dwc_search(catalognumber);

-- likewise temp_fragmentid
create index temp_dwc_searchfragid on temp_dwc_search(temp_fragmentid);

-- 00220822 has prepmethod = 'Protolog' ???
update temp_dwc_search set dc_type = 'http://purl.org/dc/dcmitype/StillImage' where temp_prepmethod = 'Photograph' or temp_prepmethod = 'Drawing';
-- set collectionid to the biocol lsid for each herbarium
-- (using the non-resolvable lsid per AppleCore guidance)
-- Prepend http://biocol.org/ to make resolvable:
-- e.g. http://biocol.org/urn:lsid:biocol.org:col:15631
-- about 5 sec each
update temp_dwc_search set collectionid = 'urn:lsid:biocol.org:col:15406' where collectioncode = 'A';
update temp_dwc_search set collectionid = 'urn:lsid:biocol.org:col:15631' where collectioncode = 'GH';
update temp_dwc_search set collectionid = 'urn:lsid:biocol.org:col:15408' where collectioncode = 'AMES';
update temp_dwc_search set collectionid = 'urn:lsid:biocol.org:col:15407' where collectioncode = 'ECON';
update temp_dwc_search set collectionid = 'urn:lsid:biocol.org:col:13199' where collectioncode = 'FH';
update temp_dwc_search set collectionid = 'urn:lsid:biocol.org:col:15868' where collectioncode = 'NEBC';

-- collectornumber
-- 25 sec
update temp_dwc_search left join fragment on temp_dwc_search.temp_fragmentid = fragment.fragmentid left join collectionobject on fragment.collectionobjectid = collectionobject.collectionobjectid left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid set temp_dwc_search.collectornumber = collectingevent.stationfieldnumber, temp_dwc_search.samplingprotocol = collectingevent.method;
-- collector, assumes currently true state of one collector per collecting event, thus concatenation of list of collectors
-- ordered by isprimary and ordernumber are not needed.
-- 1 min 7sec.
update temp_dwc_search left join fragment on temp_dwc_search.temp_fragmentid = fragment.fragmentid left join collectionobject on fragment.collectionobjectid = collectionobject.collectionobjectid left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid left join collector on collectingevent.collectingeventid = collector.collectingeventid left join agentvariant on collector.agentid = agentvariant.agentid set temp_dwc_search.collector = trim(concat(agentvariant.name,' ',ifnull(collector.etal,''))) where agentvariant.vartype = 4;

-- Project
-- Works currently, will have problems if a specimen is involved in more than one project.  Should replace with a function.
-- 21 sec
update temp_dwc_search
left join project_colobj pc on temp_dwc_search.collectionobjectid = pc.collectionobjectid
left join project p on pc.projectid = p.projectid
set temp_dwc_search.temp_projectid = p.projectid, temp_dwc_search.temp_projectname = p.projectname;
-- 1 min 48 sec.
create index temp_dwc_search_projid on temp_dwc_search(temp_projectid);

-- sex
-- 25 sec
update temp_dwc_search left join fragment on temp_dwc_search.temp_fragmentid = fragment.fragmentid set temp_dwc_search.sex = fragment.sex;
-- fit terms to googlecode DarwinCore recommended list http://code.google.com/p/darwincore/wiki/Occurrence
-- 18 sec
update temp_dwc_search set sex = 'undetermined' where sex = 'not determined';
-- phenology, mapped to dwc:reproductiveStatus
-- 29 sec
update temp_dwc_search left join fragment on temp_dwc_search.temp_fragmentid = fragment.fragmentid set temp_dwc_search.reproductivestatus = fragment.phenology;
-- Preparation Types for all preparations associated with collection object.
-- 2 min 10 sec.
update temp_dwc_search left join fragment on temp_dwc_search.temp_fragmentid = fragment.fragmentid set temp_dwc_search.preparations = concatPrepTypes(fragment.collectionobjectid);

-- Date Collected
-- Verbatim date
-- 50 sec
update temp_dwc_search
  left join fragment on temp_dwc_search.temp_fragmentid = fragment.fragmentid
  left join collectionobject on fragment.collectionobjectid = collectionobject.collectionobjectid
  left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid
  set temp_dwc_search.verbatimdate = collectingevent.verbatimdate where collectingevent.verbatimdate is not null;
-- Date function queries are slow on joins, copy fields to temp table to run functions there rather than on join
-- 1 min 44 sec.
update temp_dwc_search t
  left join fragment on t.temp_identifier = fragment.identifier
  left join collectionobject on fragment.collectionobjectid = collectionobject.collectionobjectid
  left join collectingevent ce on collectionobject.collectingeventid = ce.collectingeventid
  set t.temp_startdate = ce.startdate, t.temp_enddate = ce.enddate, t.temp_startdateprecision = ce.startdateprecision, t.temp_enddateprecision = ce.enddateprecision;
-- Start date only, year (precision=3)
-- 15 sec.
update temp_dwc_search
  set temp_dwc_search.eventdate = year(temp_startdate), temp_dwc_search.year = year(temp_startdate), temp_dwc_search.startdatecollected = temp_startdate, temp_dwc_search.enddatecollected = date_add(temp_startdate, interval 12 month)
  where temp_startdate is not null
    and temp_enddate is null and temp_startdateprecision = 3;
-- Start date only, month (precision=2)
-- 13 sec.
update temp_dwc_search
  set temp_dwc_search.eventdate = date_format(temp_startdate,'%Y-%m'), temp_dwc_search.year = year(temp_startdate), temp_dwc_search.month = month(temp_startdate), temp_dwc_search.startdatecollected = temp_startdate, temp_dwc_search.enddatecollected = date_add(temp_startdate, interval 1 month)  where temp_startdate is not null and temp_enddate is null and temp_startdateprecision = 2;
-- Start date only, day (precision=1)
-- 20 sec.
update temp_dwc_search set temp_dwc_search.eventdate = date_format(temp_startdate,'%Y-%m-%d'), temp_dwc_search.year = year(temp_startdate), temp_dwc_search.month = month(temp_startdate), temp_dwc_search.day = day(temp_startdate), temp_dwc_search.startdatecollected = temp_startdate, temp_dwc_search.enddatecollected = temp_startdate  where temp_startdate is not null and temp_enddate is null and temp_startdateprecision = 1;
-- Start and end dates
-- Current apple core guidance: don't provide year,month,day for ranges
-- 12 sec.
update temp_dwc_search
   set temp_dwc_search.eventdate = concat(
               date_format(temp_startdate,
                   case when temp_startdateprecision=1 then '%Y-%m-%d'
                         when temp_startdateprecision=2 then '%Y-%m'
                         when temp_startdateprecision=3 then '%Y'
                     end),
           if( temp_enddate is null,
                     '',
                     concat( '/',
                             date_format(temp_enddate,
                                  case when temp_enddateprecision=1 then '%Y-%m-%d'
                                       when temp_enddateprecision=2 then '%Y-%m'
                                       when temp_enddateprecision=3 then '%Y'
                                   end)))),
       temp_dwc_search.startdatecollected = temp_startdate, temp_dwc_search.enddatecollected = temp_enddate
   where temp_startdate is not null and temp_enddate is not null;

-- Habitat
-- 23 sec
update temp_dwc_search left join fragment on temp_dwc_search.temp_fragmentid = fragment.fragmentid left join collectionobject on fragment.collectionobjectid = collectionobject.collectionobjectid left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid set temp_dwc_search.habitat = collectingevent.remarks where collectingevent.remarks is not null;

-- higher geography
-- 20 sec.
-- update temp_dwc_search left join fragment on temp_dwc_search.temp_fragmentid = fragment.fragmentid left join collectionobject on fragment.collectionobjectid = collectionobject.collectionobjectid left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid left join locality on collectingevent.localityid = locality.localityid left join geography g on locality.geographyid = g.geographyid set temp_dwc_search.temp_geographyid = g.geographyid, temp_dwc_search.temp_geonodenumber = g.nodenumber, temp_dwc_search.temp_geohighestchildnodenumber =  g.highestchildnodenumber where g.geographyid is not null;
-- Takes about 1 hour for 30,000 specimens.  Too slow to use.
-- update temp_dwc_search set temp_dwc_search.highergeography = concatHigherGeography(temp_geonodenumber,temp_geohighestchildnodenumber) where temp_geographyid is not null;

-- Depends on fields added to temp_geography above.
-- 1 min.
update temp_dwc_search
  left join fragment on temp_dwc_search.temp_fragmentid = fragment.fragmentid
  left join collectionobject on fragment.collectionobjectid = collectionobject.collectionobjectid
  left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid
  left join locality on collectingevent.localityid = locality.localityid
  left join geography g on locality.geographyid = g.geographyid
  set temp_dwc_search.temp_geographyid = g.geographyid
  where g.geographyid is not null;
-- 46 sec.
update temp_dwc_search d left join temp_geography g on d.temp_geographyid = g.geographyid
set
   d.continent = g.continent,
   d.country = g.country,
   d.stateprovince = g.state,
   d.county = g.county,
   d.islandgroup = trim(concat(ifnull(g.archipelago,''),' ',ifnull(g.subcontinentislands,''))),
   d.island = g.subcountryislands,
   d.highergeography = concat(
   ifnull(concat(g.continent,';'),''),
   ifnull(concat(g.archipelago,';'),''),
   ifnull(concat(g.country,';'),''),
   ifnull(concat(g.land,';'),''),
   ifnull(concat(g.territory,';'),''),
   ifnull(concat(g.subcontinentislands,';'),''),
   ifnull(concat(g.continentsubregion,';'),''),
   ifnull(concat(g.countrysubregion,';'),''),
   ifnull(concat(g.straights,';'),''),
   ifnull(concat(g.subcountryislands,';'),''),
   ifnull(concat(g.state,';'),''),
   ifnull(concat(g.county,';'),'')
 ) where d.temp_geographyid is not null;

update temp_dwc_search d
  left join temp_geography g on d.country = g.country
        and g.rankid = 200
  set d.countrycode = g.geographycode;

-- 25 sec
update temp_dwc_search d left join temp_geography g on d.temp_geographyid = g.geographyid
set d.municipality = g.name where d.temp_geographyid is not null and g.rankid = 500 or g.rankid = 510;
-- 8 sec
update temp_dwc_search d left join temp_geography g on d.temp_geographyid = g.geographyid
set d.island = concat(d.island,'; ', g.name) where d.temp_geographyid is not null and g.rankid = 450 and d.island is not null;
-- 21 sec
update temp_dwc_search d left join temp_geography g on d.temp_geographyid = g.geographyid
set d.island = g.name where d.temp_geographyid is not null and g.rankid = 450 and d.island is null;

-- Locality
-- 1 min 10 sec
update temp_dwc_search
  left join fragment on temp_dwc_search.temp_fragmentid = fragment.fragmentid
  left join collectionobject on fragment.collectionobjectid = collectionobject.collectionobjectid
  left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid
  left join locality on collectingevent.localityid = locality.localityid
  set temp_dwc_search.locality = locality.localityname,
      temp_dwc_search.unredacted_locality = locality.localityname,
      temp_dwc_search.localityremarks = locality.remarks
  where locality.localityid is not null;
-- Verbatim locality, not available in HUH data.

-- elevation.
-- 38 sec
update temp_dwc_search left join fragment on temp_dwc_search.temp_fragmentid = fragment.fragmentid left join collectionobject on fragment.collectionobjectid = collectionobject.collectionobjectid left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid left join locality on collectingevent.localityid = locality.localityid  set temp_dwc_search.verbatimelevation = locality.verbatimelevation, temp_dwc_search.minimumelevationmeters = locality.minelevation, temp_dwc_search.maximumelevationmeters = locality.maxelevation where locality.localityid is not null;

-- georeference
-- 36 sec
update temp_dwc_search
  left join fragment on temp_dwc_search.temp_fragmentid = fragment.fragmentid
  left join collectionobject on fragment.collectionobjectid = collectionobject.collectionobjectid
  left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid
  left join locality on collectingevent.localityid = locality.localityid
  set temp_dwc_search.decimallongitude = locality.longitude1,
      temp_dwc_search.decimallatitude = locality.latitude1,
      temp_dwc_search.geodeticdatum = locality.datum,
      temp_dwc_search.unredacted_decimallongitude = locality.longitude1,
      temp_dwc_search.unredacted_decimallatitude = locality.latitude1,
      temp_dwc_search.coordinateUncertaintyInMeters = locality.latlongaccuracy
  where locality.localityid is not null;

-- Per darwincore google code recomendations, use EPSG codes for datum.
-- This doesn't sound right, as EPSG codes specify the entire coordinate reference system, not just the datum.
-- 15 to 35 sec each
update temp_dwc_search set geodeticdatum = 'EPSG:4326' where geodeticdatum = 'WGS84';
update temp_dwc_search set geodeticdatum = 'EPSG:4326' where geodeticdatum = 'WGS 84';
update temp_dwc_search set geodeticdatum = 'EPSG:4269' where geodeticdatum = 'NAD83';
update temp_dwc_search set geodeticdatum = 'EPSG:4267' where geodeticdatum = 'NAD27';
update temp_dwc_search set geodeticdatum = 'unknown' where (geodeticdatum is null or geodeticdatum = '') and decimallatitude is not null;

-- Typification or most recent determination.
-- Get the id of a typification or the most recent determination
-- 2 min
update temp_dwc_search left join fragment on temp_dwc_search.temp_fragmentid = fragment.fragmentid set temp_dwc_search.temp_determinationid = getCurrentDetermination(fragment.fragmentid);
-- 53 sec
create index temp_dwc_search_tempdetid on temp_dwc_search(temp_determinationid);
-- 11 sec
update temp_dwc_search left join determination on temp_dwc_search.temp_determinationid = determination.determinationid
  set temp_dwc_search.dateidentified =
      date_format(determination.determineddate,
        case when determineddateprecision=1 then '%Y-%m-%d'
             when determineddateprecision=2 then '%Y-%m'
             when determineddateprecision=3 then '%Y'
      end)
  where temp_dwc_search.temp_determinationid is not null and determination.determineddate is not null;
-- 14 sec (if picklistitem.value has an index added)
update temp_dwc_search left join determination on temp_dwc_search.temp_determinationid = determination.determinationid
       left join picklistitem on determination.typestatusname = picklistitem.value
  set temp_dwc_search.typestatus = picklistitem.title
  where determination.typestatusname is not null;

-- text 1 is the determiner name for non-types, the type status verifier for types.
-- 3 sec
update temp_dwc_search left join determination on temp_dwc_search.temp_determinationid = determination.determinationid
  set temp_dwc_search.identifiedby = determination.text1
  where determination.typestatusname is null and temp_dwc_search.temp_determinationid is not null;

-- determination qualifier
-- 12 sec
update temp_dwc_search left join determination on temp_dwc_search.temp_determinationid = determination.determinationid
   join
      (select * from picklistitem where PickListID=(select PickListID from picklist where Name="HUH Determination Qualifier")) pli
  on determination.qualifier = pli.title
  set temp_dwc_search.identificationqualifier = pli.title
  where temp_dwc_search.temp_determinationid is not null and determination.qualifier is not null;

-- determination remarks
-- 12 sec
update temp_dwc_search left join determination on temp_dwc_search.temp_determinationid = determination.determinationid
  set temp_dwc_search.identificationremarks
       = case when (determination.typestatusname is null)
                 then trim(concat(ifnull(determination.remarks,''),' ',ifnull(determination.text2,'')))
              when (determination.typestatusname is null)
                then trim(concat(ifnull(determination.remarks,''),' ',ifnull(determination.text2,''),' ',ifnull(concat('Verified by: ',determination.text1),'')))
         end
  where temp_dwc_search.temp_determinationid is not null;

-- determination references
-- 3 sec
update temp_dwc_search t left join determinationcitation dc on t.temp_determinationid = dc.determinationid
         left join referencework crw on dc.referenceworkid = crw.referenceworkid
  set identificationreferences = trim(concat( concatAuthors(crw.referenceworkid), ' ',  ifnull(concat(dc.Text2, '. '), ''), ifnull(concat(crw.title,' '),''),  ifnull(concat(dc.Text1, ".  "), "") ))
  where crw.referenceworkid is not null;


-- scientificname
-- scientificnameauthorship
-- 4 min 16 sec
update temp_dwc_search left join determination on temp_dwc_search.temp_determinationid = determination.determinationid
       left join taxon on determination.taxonid = taxon.taxonid
       set temp_dwc_search.scientificname = trim(concat(taxon.fullname,' ',ifnull(taxon.author,''))),
           temp_dwc_search.scientificnameauthorship = taxon.author
       where taxon.taxonid is not null;

-- update taxon info from temp_taxon table
-- 5 min
update temp_dwc_search left join determination on temp_dwc_search.temp_determinationid = determination.determinationid
       left join taxon on determination.taxonid = taxon.taxonid
       left join temp_taxon on taxon.nodenumber = temp_taxon.nodenumber
       set temp_dwc_search.taxonomicgroup = temp_taxon.groupnumber,
           temp_dwc_search.family = temp_taxon.family,
           temp_dwc_search.genus = temp_taxon.genus,
           temp_dwc_search.specificepithet =
             case
               when (temp_taxon.rankid = 220) then temp_taxon.name
               when (temp_taxon.rankid > 220) then temp_taxon.species
               else null
             end,
           temp_dwc_search.infraspecificepithet =
             case
               when (temp_taxon.rankid > 220) then temp_taxon.name
               else null
             end
       where taxon.taxonid is not null;

-- Run setCitesChildren(); to make sure that all children of cites genera/families are also marked as cites listed, then queries can run
-- on the children without having to look up the parents.
-- information witheld
-- 2 sec
-- update temp_dwc_search left join determination on temp_dwc_search.temp_determinationid = determination.determinationid
--       left join taxon on determination.taxonid = taxon.taxonid
--       set locality = '[Redacted]', informationwitheld = 'Locality redacted.  CITES Listed Taxon.'
--       where taxon.citesstatus != 'None' and locality is not null;
--  update temp_dwc_search left join determination on temp_dwc_search.temp_determinationid = determination.determinationid
--       set locality = '[Redacted]', informationwitheld = 'Locality redacted.  Cites Listed Taxon.'
--       where hasCitesParent(determination.taxonid) and locality is not null;

-- data generalizations
-- 1 sec.
update temp_dwc_search left join determination on temp_dwc_search.temp_determinationid = determination.determinationid
       left join taxon on determination.taxonid = taxon.taxonid
       set decimallatitude = floor(decimallatitude*10)/10, decimallongitude = floor(decimallongitude*10)/10,
       datageneralizations = 'Latitude and longitude rounded to 0.1 degrees.  CITES Listed Taxon.'
       where taxon.citesstatus != 'None' and decimallatitude is not null and decimallongitude is not null;
--  update temp_dwc_search left join determination on temp_dwc_search.temp_determinationid = determination.determinationid
--       set decimallatitude = floor(decimallatitude*10)/10, decimallongitude = floor(decimallongidude*10)/10,
--       datageneralizations = 'Latitude and longitude rounded to 0.1 degrees.  Cites Listed Taxon.'
--       where hasCitesParent(determination.taxonid) and decimallatitude is not null and decimallongitude is not null;

-- Redact other sensitive information
-- esastatus = 'controlled' = on DEA controlled substance list.
update temp_dwc_search left join determination on temp_dwc_search.temp_determinationid = determination.determinationid
       left join taxon on determination.taxonid = taxon.taxonid
       set scientificname = 'Redacted'
       where taxon.esastatus is not null;

delete from temp_dwc_search where scientificname = 'Redacted';

-- Update dynamic properties with various info as JSON
update temp_dwc_search
set dynamicproperties = concat('{"huh_taxonomic_group": "',COALESCE(taxonomicgroup,'null'),'", "huh_project_id": ',COALESCE(temp_projectid,'null'),', "huh_project_name": ',if(temp_projectname is null,'null', concat('"',temp_projectname,'"')),'}');


-- Get image metadata
update temp_dwc_search
  left join IMAGE_SET_collectionobject
    on temp_dwc_search.collectionobjectid = IMAGE_SET_collectionobject.collectionobjectid
  left join IMAGE_SET
    on IMAGE_SET_collectionobject.imagesetid = IMAGE_SET.id
  left join IMAGE_BATCH
    on IMAGE_BATCH.id = IMAGE_SET.batch_id
  left join IMAGE_OBJECT
    on IMAGE_SET.id = IMAGE_OBJECT.image_set_id and
	   IMAGE_OBJECT.object_type_id = 4 and
       IMAGE_OBJECT.active_flag = 1
  set hasimage = 1,
      imagedescription = IMAGE_SET.description,
      imagecreatedate = IMAGE_BATCH.production_date,
      imagecreator = IMAGE_BATCH.photographer_name,
      imageuri = concat('http://data.huh.harvard.edu/',temp_dwc_search.fragmentguid,'/image')
  where IMAGE_OBJECT.URI is not null;


-- othercatalognumbers  only providing accession number if present.
-- Do other identifiers go here as well, or does their project based nature put them elsewhere?
-- 45 sec
update temp_dwc_search left join fragment on temp_dwc_search.temp_fragmentid = fragment.fragmentid
   set temp_dwc_search.othercatalognumbers = concat(fragment.text1,'-accession-',fragment.accessionnumber)
   where fragment.accessionnumber is not null;

-- Add indexes to dwc_search (but on temp_ not table, as there isn't an if not exists yet in MySQL
-- 1 min 45 sec
create index dwc_country on temp_dwc_search(country);
-- 1 min 40 sec
create index dwc_search_collobjectid on temp_dwc_search(collectionobjectid);


-- Create identification history table
create table if not exists temp_dwc_identification_history (
  autoid bigint not null primary key auto_increment,
  fragmentguid char(100), -- dwc_search.fragmentguid
  determinationid bigint,
  identificationid varchar(255), -- todo: future work, need to generate guids
  identifiedby text, -- determination.text1
  dateidentified varchar(50), -- determination.determineddate
  identificationqualifier varchar(50), -- determination.qualifier, picklistitem.title
  remarks text, -- determination.remarks | determination.text2
  identificationreferences text, -- referencework.*
  typestatus varchar(50), -- determination.typestatusname, picklistitem.title
  verificationstatus text, -- todo: future work
  scientificname varchar(255),
  scientificnameauthorship varchar(128),
  specificepithet varchar(255),
  infraspecificepithet varchar(255),
  genus  varchar(255),
  family varchar(255),
  taxonrank varchar(255)
) ENGINE MyISAM CHARACTER SET utf8;

delete from temp_dwc_identification_history;

-- fill in identification records
insert ignore into temp_dwc_identification_history (fragmentguid, determinationid, remarks)
  select temp_dwc_search.fragmentguid, determination.determinationid, determination.remarks
  from temp_dwc_search, determination
  where temp_dwc_search.temp_fragmentid = determination.fragmentid;

create index temp_dwc_identification_history_detid on temp_dwc_identification_history(determinationid);

update temp_dwc_identification_history
  left join determination on temp_dwc_identification_history.determinationid = determination.determinationid
  set temp_dwc_identification_history.dateidentified =
      date_format(determination.determineddate,
        case when determineddateprecision=1 then '%Y-%m-%d'
             when determineddateprecision=2 then '%Y-%m'
             when determineddateprecision=3 then '%Y'
      end)
  where determination.determineddate is not null;

update temp_dwc_identification_history left join determination on temp_dwc_identification_history.determinationid = determination.determinationid
  left join picklistitem on determination.typestatusname = picklistitem.value
  set temp_dwc_identification_history.typestatus = picklistitem.title
  where determination.typestatusname is not null;

update temp_dwc_identification_history left join determination on temp_dwc_identification_history.determinationid = determination.determinationid
  set temp_dwc_identification_history.identifiedby = determination.text1
  where determination.typestatusname is null;

-- determination qualifier
update temp_dwc_identification_history left join determination on temp_dwc_identification_history.determinationid = determination.determinationid
  join (select * from picklistitem where PickListID=(select PickListID from picklist where Name="HUH Determination Qualifier")) pli
  on determination.qualifier = pli.title
  set temp_dwc_identification_history.identificationqualifier = pli.title
  where determination.qualifier is not null;

-- determination references
update temp_dwc_identification_history t left join determinationcitation dc on t.determinationid = dc.determinationid
  left join referencework crw on dc.referenceworkid = crw.referenceworkid
  set identificationreferences = trim(concat( concatAuthors(crw.referenceworkid), ' ',  ifnull(concat(dc.Text2, '. '), ''), ifnull(concat(crw.title,' '),''),  ifnull(concat(dc.Text1, ".  "), "") ))
  where crw.referenceworkid is not null;

-- scientificname
-- scientificnameauthorship
-- 4 min 16 sec
update temp_dwc_identification_history left join determination on temp_dwc_identification_history.determinationid = determination.determinationid
       left join taxon on determination.taxonid = taxon.taxonid
       set temp_dwc_identification_history.scientificname = trim(concat(taxon.fullname,' ',ifnull(taxon.author,''))),
           temp_dwc_identification_history.scientificnameauthorship = taxon.author
       where taxon.taxonid is not null;

-- update taxon info from temp_taxon table
-- 5 min
update temp_dwc_identification_history left join determination on temp_dwc_identification_history.determinationid = determination.determinationid
       left join taxon on determination.taxonid = taxon.taxonid
       left join temp_taxon on taxon.nodenumber = temp_taxon.nodenumber
       left join taxontreedefitem on taxon.rankid = taxontreedefitem.rankid and taxontreedefitem.taxontreedefid = 1
       set temp_dwc_identification_history.family = temp_taxon.family,
           temp_dwc_identification_history.genus = temp_taxon.genus,
           temp_dwc_identification_history.specificepithet =
             case
               when (temp_taxon.rankid = 220) then temp_taxon.name
               when (temp_taxon.rankid > 220) then temp_taxon.species
               else null
             end,
           temp_dwc_identification_history.infraspecificepithet =
             case
               when (temp_taxon.rankid > 220) then temp_taxon.name
               else null
             end,
           temp_dwc_identification_history.taxonrank = taxontreedefitem.name
       where taxon.taxonid is not null;


-- switch out the dwc_search tables for the newly build temp_dwc_search tables
-- create a placeholder for first run of script.
create table if not exists dwc_search (id int);
create table if not exists dwc_identification_history (id int);
drop table if exists old_dwc_search;
drop table if exists old_dwc_identification_history;
rename table dwc_search to old_dwc_search, temp_dwc_search to dwc_search, dwc_identification_history to old_dwc_identification_history, temp_dwc_identification_history to dwc_identification_history;

-- Clean up.  Remove the previous copies of the tables.
drop table old_dwc_search;
drop table old_dwc_identification_history;


--  Tables to support pages built from slow group by searches.
--  These tables cache the results of the group by queries.  See: function browse() in specify_library.php


--  Create a cache for the family page:
create table temp_cache_family as select count(distinct w.collectionobjectid) as cocount, family, count(distinct i.imagesetid) as imcount from web_search w left join IMAGE_SET_collectionobject i on w.collectionobjectid = i.collectionobjectid  group by family;

create table if not exists cache_family (id int);
rename table cache_family to old_cache_family, temp_cache_family to cache_family;
drop table old_cache_family;

--  Create a cache for the country page:
create table temp_cache_country as select count(distinct w.collectionobjectid) as cocount, country, count(distinct i.imagesetid) as imcount from web_search w left join IMAGE_SET_collectionobject i on w.collectionobjectid = i.collectionobjectid  group by country;

create table if not exists cache_country (id int);
rename table cache_country to old_cache_country, temp_cache_country to cache_country;
drop table old_cache_country;



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
