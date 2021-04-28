select * from geography where name = 'United States of America';
select * from geography where parentid = 6598;

-- create batchees
insert into TR_BATCH (path, image_batch_id) select concat('US - ', name), geographyid from geography where parentid = 6598;

select * from TR_BATCH order by tr_batch_id desc; -- get batch id for below

-- main function, insert records - modify conditions as needed
SELECT @i:=0;
insert into TR_BATCH_IMAGE (tr_batch_id, image_object_id, barcode, position) 
     select trb.tr_batch_id, imo.ID, f.identifier, @i:=@i+1
from fragment f, IMAGE_SET_collectionobject imsc, IMAGE_OBJECT imo, collectionobject co, collectingevent ce, collector c, locality l, geography g, determination d, taxon t, TR_BATCH trb, geography gp
where f.collectionobjectid = imsc.collectionobjectid
  and imsc.imagesetid = imo.IMAGE_SET_ID
  and imo.active_flag = 1
  and imo.object_type_id = 3
-- modify below here for batch conditions
  and d.fragmentid = f.fragmentid
  and d.taxonid = t.taxonid
  and f.collectionobjectid = co.collectionobjectid
  and co.collectingeventid = ce.collectingeventid
  and ce.collectingeventid = c.collectingeventid
  and ce.localityid = l.localityid
  and l.geographyid = g.geographyid
  and (l.localityname = '[data not captured]' or c.agentid = 97236) -- agentid for [data not captured] = 97236
  and d.yesno3 = '1' -- filedunder = true
  and trb.path like 'US - %'
  and trb.image_batch_id = gp.geographyid
  and g.nodenumber >= gp.nodenumber
  and g.nodenumber <= gp.highestchildnodenumber
group by f.identifier
order by t.fullname, f.identifier
; 
  
-- check count
select tr.*, count(*) from TR_BATCH tr, TR_BATCH_IMAGE tri where tr.tr_batch_id = tri.tr_batch_id group by tr.tr_batch_id order by tr_batch_id desc;

-- delete batch images if there is a mistake
delete from TR_BATCH_IMAGE where tr_batch_id = 5432;

-- resequence records starting from 1
alter table TR_BATCH_IMAGE add column temp int;

SELECT @i:=0;
update TR_BATCH_IMAGE trbi
set trbi.position = if(@prev=trbi.tr_batch_id, @i:=@i+1, @i:=1), temp=@prev:=trbi.tr_batch_id
where trbi.tr_batch_id >= 5485
order by trbi.tr_batch_id, trbi.position;

alter table TR_BATCH_IMAGE drop column temp;

select * from TR_BATCH_IMAGE order by tr_batch_id desc, position asc;
