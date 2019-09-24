delimiter $$

DROP PROCEDURE IF EXISTS call_tr_setup $$
CREATE PROCEDURE call_tr_setup()
  BEGIN

DECLARE done int DEFAULT 0;
DECLARE trbatchid int;
DECLARE trbatchname nvarchar(255);
DECLARE myCursor CURSOR FOR
    SELECT tr_batch_id, path FROM TR_BATCH where tr_batch_id not in (select tr_batch_id from TR_BATCH_IMAGE); 
DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;    
    
OPEN myCursor;
FETCH myCursor INTO trbatchid, trbatchname;
WHILE done = 0 DO
    CALL setup_tr_batch(trbatchid, trbatchname);
    FETCH myCursor INTO trbatchid, trbatchname;
END WHILE;
CLOSE myCursor;
  
END; $$
delimiter ;


-- call call_tr_setup;

