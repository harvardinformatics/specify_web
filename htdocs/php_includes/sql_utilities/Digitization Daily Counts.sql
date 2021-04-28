-- counts for last week

-- created records
select a.firstname fn, a.lastname ln, cal.m, cal.d, cal.dayName, coalesce(count(f.fragmentid),0) c
from calendar cal
inner join agent a on a.agentid in (119120, 5, 73, 115037, 94974, 115146, 115594, 119119, 53, 97302, 22, 2, 116253, 118855, 92283, 118862)
left join fragment f on f.createdbyagentid = a.agentid
					and cast(f.timestampcreated as date) = cal.dt
where cal.dt >= '2020-04-05'
  and cal.dt <= '2020-04-11'
group by fn, ln, m, d		
order by fn, ln, m, d
;

-- modified records
select a.firstname fn, a.lastname ln, cal.m, cal.d, cal.dayName, coalesce(count(f.fragmentid),0) c
from calendar cal
inner join agent a on a.agentid in (119120, 5, 73, 115037, 94974, 115146, 115594, 119119, 53, 97302, 22, 2, 116253, 118855, 92283, 118862)
left join fragment f on f.modifiedbyagentid = a.agentid
					and cast(f.timestampmodified as date) = cal.dt
                    and cast(f.timestampmodified as date) > cast(f.timestampcreated as date)
where cal.dt >= '2020-04-05'
  and cal.dt <= '2020-04-11'
group by fn, ln, m, d		
order by fn, ln, m, d
;

-- --------old
select t.a, ag.firstname, ag.lastname, t.m, t.d, count(*) c from 

(
select f.createdbyagentid a, month(f.timestampcreated) m, day(f.timestampcreated) d
from fragment f
where f.timestampcreated >= '2020-04-05' and f.timestampcreated <= '2020-04-11'
UNION ALL
select f.modifiedbyagentid a, month(f.timestampmodified) m, day(f.timestampmodified) d 
from fragment f
where f.timestampmodified >= '2020-04-05' and f.timestampmodified <= '2020-04-11'
and cast(f.timestampmodified as date) > cast(f.timestampcreated as date)
) t, agent ag
where t.a = ag.agentid
group by t.m, t.d, t.a
having c > 9
order by ag.firstname, ag.lastname, t.m, t.d;

-- Monthly averages
select t.a, ag.firstname, ag.lastname, t.m, t.d, count(*) c from 

(
select f.createdbyagentid a, month(f.timestampcreated) m, day(f.timestampcreated) d
from fragment f
where f.timestampcreated >= '2020-01-01'
UNION ALL
select f.modifiedbyagentid a, month(f.timestampmodified) m, day(f.timestampmodified) d 
from fragment f
where f.timestampmodified >= '2020-01-01'
and cast(f.timestampmodified as date) > cast(f.timestampcreated as date)
) t, agent ag
where t.a = ag.agentid
group by t.a, t.m, t.d
having c > 9
order by t.a, t.m, t.d;


select f.createdbyagentid a, month(f.timestampcreated) m, day(f.timestampcreated) d
from fragment f
where f.timestampcreated >= '2020-01-01'
union
select f.modifiedbyagentid a, month(f.timestampmodified) m, day(f.timestampmodified) d 
from fragment f
where f.timestampmodified >= '2020-01-01'
and cast(f.timestampmodified as date) > cast(f.timestampcreated as date)
order by a, m, d;


select collectionobjectid from fragment where identifier ='00003871';
select * from IMAGE_SET_collectionobject where collectionobjectid = 73723;
select * from IMAGE_OBJECT where IMAGE_SET_ID in (375507, 389742);