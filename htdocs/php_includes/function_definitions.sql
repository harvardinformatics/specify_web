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

-- Add indexes to specify tables:
-- add index to taxon(rankid) to find particular ranks
-- create index idx_taxon_rankid on taxon(rankid);
-- other indexes
-- create index idx_agent_specialty_role on agentspecialty(role);
-- create index idx_agent_specialty_name on agentspecialty(specialtyname);
-- create index idx_taxontreedefitem_name on taxontreedefitem(name);

-- index on picklistitem to allow lookup of names from values.
-- create index picklistitemvalue on picklistitem(value);


-- Suporting functions that can be used by web interface to specify
-- Innefficient to use in searches of data sets, but effective for data
-- retrieval for details of individual collection object records.
--
-- READS SQL DATA and DETERMINISTIC are needed if query is run on a
-- master of a replication set of specify instances where binary logging
-- is enabled.

drop function if exists specify.getHigherTaxonOfRank;
drop function if exists specify.getGeographyOfRank;
drop function if exists specify.getTextDate;
drop function if exists specify.getAgentName;

delimiter |
create function specify.getHigherTaxonOfRank(rank_id INT, highestchild INT, nodenum INT)
returns VARCHAR(255)
READS SQL DATA
BEGIN
   declare t_name varchar(255);
   select name into t_name from taxon  where highestchildnodenumber >= highestchild and nodenumber <= nodenum and rankid = rank_id limit 1;
   return t_name;
END |
delimiter ;

delimiter |
create function specify.getGeographyOfRank(rank_id INT, highestchild INT, nodenum INT)
returns VARCHAR(255)
READS SQL DATA
BEGIN
   declare t_name varchar(255);
   select name into t_name from geography where highestchildnodenumber >= highestchild and nodenumber <= nodenum and rankid = rank_id limit 1;
   return t_name;
END |
delimiter ;

delimiter |
create function specify.getTextDate(aDate DATE, datePrecision INT)
returns VARCHAR(30)
DETERMINISTIC
CONTAINS SQL
BEGIN
   declare t_result varchar(255) default '';
   case datePrecision
      when 1 then select concat(year(aDate), "-", month(aDate), "-", day(aDate))  into t_result;
      when 2 then select concat(year(aDate), "-", month(aDate)) into t_result;
      when 3 then select year(aDate) into t_result;
      else select '' into t_result;
   end case;
   return t_result;
END |
delimiter ;

delimiter |
create function specify.getAgentName(aId INT)
returns VARCHAR(255)
DETERMINISTIC
CONTAINS SQL
BEGIN
	declare t_result varchar(255) default '' ;
	select name into t_result from agentvariant where agentid = aId order by vartype desc limit 1;
	return t_result;
END |
delimiter ;

drop function if exists specify.concatPrepTypes;

delimiter |
create function specify.concatPrepTypes(aCollObjID INT)
returns text
DETERMINISTIC
CONTAINS SQL
BEGIN
    declare prep varchar(32);
    declare sep varchar(2) default '';
	declare t_result text default '' ;
	declare told_result text default '' ;
    declare done int default 0;
    declare getpreps cursor for
    select preptype.name from collectionobject left join fragment on collectionobject.collectionobjectid = fragment.collectionobjectid left join preparation preparation on fragment.preparationid = preparation.preparationid left join preptype on preparation.preptypeid = preptype.preptypeid where collectionobject.collectionobjectid = aCollObjID order by preptype.name;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    open getpreps;
    readloop: LOOP
      fetch getpreps into prep;
      if done then
        LEAVE readloop;
      end if;
      set told_result = t_result;
      set t_result = concat(told_result,sep,prep);
      set sep = ',';
    end LOOP;
	return t_result;
END |
delimiter ;

drop function if exists specify.concatHigherGeography;

delimiter |
create function specify.concatHigherGeography(aNodeNumber INT, aHighestChildNodeNumber INT)
returns text
DETERMINISTIC
CONTAINS SQL
BEGIN
    declare geo varchar(64);
    declare sep varchar(2) default '';
	declare t_result text default '' ;
	declare told_result text default '' ;
    declare done int default 0;
    declare getgeos cursor for
    select g.name from geography g where g.highestchildnodenumber >= aNodeNumber and g.nodenumber<= aHighestChildNodeNumber and g.name <> 'Earth' order by g.rankid;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    open getgeos;
    readloop: LOOP
      fetch getgeos into geo;
      if done then
        LEAVE readloop;
      end if;
      set told_result = t_result;
      set t_result = concat(told_result,sep,geo);
      set sep = '; ';
    end LOOP;
	return t_result;
END |
delimiter ;

drop function if exists specify.concatAuthors;

delimiter |
create function specify.concatAuthors(aReferenceWorkID INT)
returns text
DETERMINISTIC
CONTAINS SQL
BEGIN
    declare auth varchar(64);
    declare sep varchar(2) default '';
    declare terminator varchar(2) default '';
	declare t_result text default '';
	declare told_result text default '' ;
    declare done int default 0;
    declare getauths cursor for
    select name from author left join agentvariant on author.agentid = agentvariant.agentid where vartype = 2 and referenceworkid = aReferenceWorkID order by ordernumber asc;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    open getauths;
    readloop: LOOP
      fetch getauths into auth;
      if done then
        set told_result = t_result;
        set t_result = concat(told_result,terminator);
        LEAVE readloop;
      end if;
      set told_result = t_result;
      set t_result = concat(told_result,sep,auth);
      set sep = ', ';
      set terminator = '.';
    end LOOP;
	return t_result;
END |
delimiter ;

drop function if exists specify.getCurrentDetermination;

delimiter |
create function specify.getCurrentDetermination(aFragmentId INT)
returns bigint
DETERMINISTIC
CONTAINS SQL
BEGIN
	declare t_result bigint ;
	select determinationid into t_result from determination where fragmentid = aFragmentId order by typestatusname desc, iscurrent desc, yesno3 desc, determineddate desc limit 1;
	return t_result;
END |
delimiter ;

drop function if exists specify.hasCitesParent;

delimiter |
create function specify.hasCitesParent(aTaxonID INT)
returns int
DETERMINISTIC
CONTAINS SQL
BEGIN
    declare cites varchar(64);
	declare t_result int default 0 ;
    declare done int default 0;
    declare getcites cursor for
    select t.citesstatus from taxon t where t.highestchildnodenumber >= (select nodenumber from taxon where taxonid = aTaxonID) and t.nodenumber<= (select highestchildnodenumber from taxon where taxonid = aTaxonID);
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    open getcites;
    readloop: LOOP
      fetch getcites into cites;
      if done then
        LEAVE readloop;
      end if;
      if cites <> 'None' then
         set t_result = 1;
        LEAVE readloop;
      end if;
    end LOOP;
	return t_result;
END |
delimiter ;

drop function if exists specify.setCitesChildren;

-- needs create index taxoncites on taxon(citesstatus);

delimiter |
create function specify.setCitesChildren()
returns int
DETERMINISTIC
CONTAINS SQL
BEGIN
    declare status varchar(64);
    declare node int default 0;
    declare hcnode int default 0;
	declare t_result int default 0 ;
    declare done int default 0;
    declare getcites cursor for
    select t.nodenumber, t.highestchildnodenumber, citesstatus from taxon t where citesstatus is not null and citesstatus <> 'None' and t.nodenumber < t.highestchildnodenumber;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
    open getcites;
    readloop: LOOP
      fetch getcites into node, hcnode, status;
      if done then
        LEAVE readloop;
      end if;
      update taxon set citesstatus = status where citesstatus = 'None' and nodenumber > node and highestchildnodenumber < hcnode;
    end LOOP;
	return t_result;
END |
delimiter ;

grant execute on procedure specify.getTextDate to 'specify_web_user'@'kiki.huh.harvard.edu';
grant execute on procedure specify.getGeographyOfRank to 'specify_web_user'@'kiki.huh.harvard.edu';
grant execute on procedure specify.getHigherTaxonOfRank to 'specify_web_user'@'kiki.huh.harvard.edu';
grant execute on procedure specify.getAgentName to 'specify_web_user'@'kiki.huh.harvard.edu';
grant execute on procedure specify.concatPrepTypes to 'specify_web_user'@'kiki.huh.harvard.edu';
grant execute on procedure specify.getCurrentDetermination to 'specify_web_user'@'kiki.huh.harvard.edu';



grant execute on procedure .getTextDate to 'specify_user'@'localhost';
grant execute on procedure .getGeographyOfRank to 'specify_user'@'localhost';
grant execute on procedure .getHigherTaxonOfRank to 'specify_user'@'localhost';
grant execute on procedure .getAgentName to 'specify_user'@'localhost';
grant execute on procedure .concatPrepTypes to 'specify_user'@'localhost';
grant execute on procedure .getCurrentDetermination to 'specify_user'@'localhost';



--  Function to estimate data capture rates within projects, predicated on creating a temporary table:
--  create table temp_project_data_entry_times as select c.timestampcreated, a.lastname, p.projectname  from project_colobj pc left join project p on pc.projectid = p.projectid left join collectionobject c on pc.collectionobjectid = c.collectionobjectid left join agent a on c.createdbyagentid = a.agentid;

drop function if exists specify.getRate;

delimiter |
create FUNCTION specify.getRate
(
  username VARCHAR(200),
  project VARCHAR(50)
) RETURNS decimal
   READS SQL DATA
   BEGIN
      declare lastdate    datetime;
      declare currentdate datetime;
      declare diffmin decimal;
      declare totalseconds decimal default -1;
      declare counter decimal default 0;
      declare done int default 0;
      declare cur CURSOR for select timestampcreated
                      from temp_project_data_entry_times
                      where timestampcreated is not null
                            and lastname = username
                            and projectname = project
                      order by timestampcreated;
      DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

      OPEN cur;
       fetch cur into lastdate;
       readloop: loop
           fetch cur into currentdate;
           if DONE then
              LEAVE readloop;
           end if;
              set diffmin = timestampdiff(MINUTE,lastdate,currentdate);
              if ( diffmin < 20 ) then
                  set totalseconds = totalseconds + timestampdiff(SECOND,lastdate,currentdate);
                  set counter = counter + 1;
              end if;
              set lastdate = currentdate;
       end loop;
       close cur;

       return totalseconds/counter;
END |
delimiter ;
