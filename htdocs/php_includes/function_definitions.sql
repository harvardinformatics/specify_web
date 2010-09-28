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
create index idx_taxon_rankid on taxon(rankid);
-- other indexes 
create index idx_agent_specialty_role on agentspecialty(role);
create index idx_agent_specialty_name on agentspecialty(specialtyname);
create index idx_taxontreedefitem_name on taxontreedefitem(name);

-- Suporting functions that can be used by web interface to specify
-- Innefficient to use in searches of data sets, but effective for data
-- retrieval for details of individual collection object records.
-- 
-- READS SQL DATA and DETERMINISTIC are needed if query is run on a
-- master of a replication set of specify instances where binary logging
-- is enabled.

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

grant execute on procedure specify.getTextDate to 'specify_web_user'@'kiki.huh.harvard.edu';
grant execute on procedure specify.getGeographyOfRank to 'specify_web_user'@'kiki.huh.harvard.edu';
grant execute on procedure specify.getHigherTaxonOfRank to 'specify_web_user'@'kiki.huh.harvard.edu';
grant execute on procedure specify.getAgentName to 'specify_web_user'@'kiki.huh.harvard.edu';