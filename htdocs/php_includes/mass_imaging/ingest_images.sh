#!/bin/bash

# Make sure that the filesystem is mounted correctly with mfsymlinks (CIFS only?)
content=$(cat /mnt/huhimagestorage/huhspecimenimages/symlink_testfile.txt)
if [ "Symlinks working!" != "$content" ];then
    echo "ERROR: huhimagestorage/ is not mounted with mfsymlinks"
    exit 1
fi

BASE_DIR="/mnt/huhimagestorage/Herbaria6/HUH-IT/from_informatics_*"
IMG_DIR="/mnt/huhimagestorage/huhspecimenimages"
USERS=(abrach bfranzone cthornton dhanrahan erullo etaylor hmerchant iferreras kbrzezinski wkittredge)
SESSION_FORMAT1="/[0-9]{4}-[0-9]{2}-[0-9]{2}$"
SESSION_FORMAT2="/[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{2}$"
BARCODE_FORMAT="^[0-9]{8}$"

for d in $BASE_DIR ; do # iterate through the directories for each photostation
	for u in ${USERS[@]} ; do # iterate through the subdirectories for each user
		DIR=$d/$u
		if [ -d $DIR ]; then # user may or may not exists for that photostation
			for sd in $DIR/* ; do # iterate through all subdirectories (each photo session)
				
				if [ ! -d $sd ] ; then
					continue
				fi
				
				if [[ $sd =~ $SESSION_FORMAT1 || $sd =~ $SESSION_FORMAT2 ]] ; then
				
					echo "Checking $sd"
					
			
					if [ -f $sd/ingest_done ]; then
						echo "Done file found, skipping $sd"
						continue
					fi
					
					if [ ! -d $sd/Output/JPG ]; then
						echo "ERROR: No JPG directory, skipping $sd"
						continue
					fi
					
					if [ ! -d $sd/Output/JPG-Preview ]; then
						echo "ERROR: No JPG-Preview directory, skipping $sd"
						continue
					fi
					
					if [ ! -d $sd/Output/JPG-Thumbnail ]; then
						echo "ERROR: No JPG-Thumbnail directory, skipping $sd"
						continue
					fi										
			
					if [ ! -d $sd/Output/DNG ]; then
						echo "ERROR: No DNG directory, skipping $sd"
						continue
					fi
				
					source image_count_check.sh $sd
					
					if [ $? != 0 ] ; then
						echo "ERROR: Check for derivatives failed, skipping $sd"
						continue
					fi
					
					BATCH_ID=$(php add_batch.php $sd) # Add batch to the database
								
					for f in $sd/Output/JPG/*.jpg ; do # use the JPGs for scanning for barcodes
						echo "Looking for barcodes in '$f'"
					
						# Extract barcodes
						BARCODES=$(zbarimg -q --raw -Sdisable -Scode39.enable "$f")
						for b in ${BARCODES[@]} ; do
						
							if [[ ! $b =~ $BARCODE_FORMAT ]] ; then
								echo "WARN: Skipping bad barcode ($b) from $f"
								continue
							fi
													
							filename=$(basename -- "$f")
							basefile="${filename%.*}"
							
							# Create symlinks for all of the files
							source link_image.sh "$sd/Output/JPG/$basefile.jpg" "$IMG_DIR/JPG" "$b" "jpg";
							
							source link_image.sh "$sd/Output/JPG-Preview/$basefile.jpg" "$IMG_DIR/JPG-Preview" "$b" "jpg";

							source link_image.sh "$sd/Output/JPG-Thumbnail/$basefile.jpg" "$IMG_DIR/JPG-Thumbnail" "$b" "jpg";
							
							source link_image.sh "$sd/Output/DNG/$basefile.dng" "$IMG_DIR/DNG" "$b" "dng";
							
							source link_image.sh "$sd/Capture/$basefile.CR2" "$IMG_DIR/RAW" "$b" "CR2";
								
							# Create database entries																									
							php add_image_set.php "$BATCH_ID" "$sd" "$b"
							
							if [ $? != 0 ] ; then
								echo "ERROR: Ingest failed for barcode $b in dir $sd"
								exit 1
							fi							

						done	
					done
									
					touch $sd/ingest_done # so we don't reprocess this directory
					
					echo "Done ingesting $sd"
				else
					echo "ERROR: Skipping badly formatted directory $sd"
				fi
				
			done
		fi
	done
done