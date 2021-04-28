create table calendar ( 
 dt date not null primary key, 
 y smallint null, 
 q tinyint null, 
 m tinyint null, 
 d tinyint null, 
 dw tinyint null, 
 monthName varchar(9) null, 
 dayName varchar(9) null, 
 w tinyint null, 
 isWeekday binary(1) null
 );
 
 -- create ints table
create table ints ( i tinyint );
-- load data into ints table
insert into ints values (0),(1),(2),(3),(4),(5),(6),(7),(8),(9);
 
insert ignore into calendar (dt) 
  select DATE('2010-01-01') + interval a.i*10000 + b.i*1000 + c.i*100 + d.i*10 + e.i day 
  from ints a 
  join ints b 
  join ints c 
  join ints d 
  join ints e 
  where (a.i*10000 + b.i*1000 + c.i*100 + d.i*10 + e.i) <= 11322 order by 1;

select * from calendar; -- where dt >= '2019-01-01';  
delete from calendar;  
  
-- load other columns in date data of calendar table
update calendar 
   set isWeekday = case when dayofweek(dt) in (1,7) then 0 else 1 end, 
       y = year(dt), 
       q = quarter(dt), 
       m = month(dt), 
       d = dayofmonth(dt), 
       dw = dayofweek(dt), 
       monthname = monthname(dt), 
       dayname = dayname(dt), 
       w = week(dt)
;