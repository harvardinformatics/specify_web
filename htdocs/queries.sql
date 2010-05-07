create table if not exists web_quicksearch (
  quicksearchid bigint primary key not null auto_increment,
  collectionobjectid bigint not null,
  searchable text
);
-- create fulltext index i_web_quicksearch on web_quicksearch(searchable);


insert into web_quicksearch (collectionobjectid, searchable) (select a.collectionobjectid, concat(geography.name, ' ', gloc.name, ' ', a.fullname, ' ', a.catalognumber) from geography, (select distinct taxon.fullname, locality.geographyid geoid, collectionobject.altcatalognumber as catalognumber, collectionobject.collectionobjectid from collectionobject left join fragment on collectionobject.collectionobjectid = fragment.collectionobjectid left join determination on fragment.fragmentid = determination.fragmentid left join taxon on determination.taxonid = taxon.taxonid left join collectingevent on collectionobject.collectingeventid = collectingevent.collectingeventid left join locality on collectingevent.localityid = locality.localityid limit 5) a left join geography gloc on a.geoid = gloc.geographyid where geography.rankid = 200 and geography.highestchildnodenumber >= a.geoid and geography.nodenumber <= a.geoid);


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
    groupfilter varchar (50)
);
 
delimiter | 
create function specify6.getParentRank(rank_id INT, highestchild INT, nodenum INT) 
returns VARCHAR(255)
BEGIN
   declare t_name varchar(255);
   select name into t_name from taxon  where highestchildnodenumber >= highestchild and nodenumber <= nodenum and rankid = rank_id;   
   return t_name;
END |
delimiter ;

insert into web_search (family,genus,species,author,collectionobjectid,barcode) select getParentRank(140,t.highestchildnodenumber,t.nodenumber) as family, getParentRank(180,t.highestchildnodenumber,t.nodenumber) as genus, t.name, t.author, c.collectionobjectid, c.altcatalognumber from determination d left join taxon t on d.taxonid = t.taxonid left join fragment f on d.fragmentid = f.fragmentid left join collectionobject c on f.collectionobjectid = c.collectionobjectid where t.rankid = 220;
