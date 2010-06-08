-- Suporting functions that can be used by web interface to specify
-- Innefficient to use in searches of data sets, but effective for data
-- retrieval for details of individual collection object records. 

delimiter | 
create function specify.getHigherTaxonOfRank(rank_id INT, highestchild INT, nodenum INT) 
returns VARCHAR(255)
BEGIN
   declare t_name varchar(255);
   select name into t_name from taxon  where highestchildnodenumber >= highestchild and nodenumber <= nodenum and rankid = rank_id limit 1;   
   return t_name;
END |
delimiter ;

delimiter | 
create function specify.getGeographyOfRank(rank_id INT, highestchild INT, nodenum INT) 
returns VARCHAR(255)
BEGIN
   declare t_name varchar(255);
   select name into t_name from geography where highestchildnodenumber >= highestchild and nodenumber <= nodenum and rankid = rank_id limit 1;   
   return t_name;
END |
delimiter ;

delimiter | 
create function specify.getTextDate(aDate DATE, datePrecision INT) 
returns VARCHAR(30)
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

grant execute on procedure specify.getTextDate to 'specify_user'@'localhost';