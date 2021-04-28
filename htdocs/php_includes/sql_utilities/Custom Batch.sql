-- create batch
insert into TR_BATCH (path) values ('dhanrahan/California-2'); -- 5432
select * from TR_BATCH order by tr_batch_id desc; -- get batch id for below

-- if based on geography, get geography node range
select * from geography where name = 'California'; -- node 7421 to 7594

-- main function, insert records - modify conditions as needed
SELECT @i:=0;
insert into TR_BATCH_IMAGE (tr_batch_id, image_object_id, barcode, position) 
     select 5432, imo.ID, f.identifier, @i:=@i+1
from fragment f, IMAGE_SET_collectionobject imsc, IMAGE_OBJECT imo, collectionobject co, collectingevent ce, collector c, locality l, geography g, determination d, taxon t
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
  and g.nodenumber >= 7421
  and g.nodenumber <= 7594
  and (l.localityname = '[data not captured]' or c.agentid = 97236) -- agentid for [data not captured] = 97236
  and d.yesno3 = '1' -- filedunder = true
order by t.fullname, f.identifier
; 
  
-- check count
select tr.*, count(*) from TR_BATCH tr, TR_BATCH_IMAGE tri where tr.tr_batch_id = tri.tr_batch_id group by tr.tr_batch_id order by tr_batch_id desc;

-- delete batch images if there is a mistake
delete from TR_BATCH_IMAGE where tr_batch_id = 5432;