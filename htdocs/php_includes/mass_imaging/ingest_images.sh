#!/bin/bash

BASE_DIR="/mnt/huhimagestorage/Herbaria6/HUH-IT/from_informatics_*"
IMG_DIR="/mnt/huhimagestorage/huhspecimenimages"
USERS=(abrach bfranzone cthornton dhanrahan erullo etaylor hmerchant iferreras kbrzezinski wkittredge)
SESSION_FORMAT1="/[0-9]{4}-[0-9]{2}-[0-9]{2}$"
SESSION_FORMAT2="/[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{2}$"

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
						echo "Alert: No JPG directory for $sd"
						continue
					fi
					
					if [ ! -d $sd/Output/JPG-Preview ]; then
						echo "Alert: No JPG-Preview directory for $sd"
						continue
					fi
					
					if [ ! -d $sd/Output/JPG-Thumbnail ]; then
						echo "Alert: No JPG-Thumbnail directory for $sd"
						continue
					fi										
			
					if [ ! -d $sd/Output/DNG ]; then
						echo "Alert: No DNG directory for $sd"
						continue
					fi
				
					source image_count_check.sh $sd
					
					if [ $? != 0 ] ; then
						echo "ALERT: Check for derivatives failed at $sd"
						continue
					fi
					
					BATCH_ID=$(php add_batch.php $sd) # Add batch to the database
								
					for f in $sd/Output/JPG/*.jpg ; do # use the JPGs for scanning for barcodes
						echo "Looking for barcodes in '$f'"
					
						# Extract barcodes
						BARCODES=$(zbarimg -q --raw -Sdisable -Scode39.enable "$f")
						for b in ${BARCODES[@]} ; do
							filename=$(basename -- "$f")
							basefile="${filename%.*}"
							
							# Create symlinks for all of the files
							if [ -f "$IMG_DIR/JPG/$b.jpg" ] ; then
								echo "ALERT: symlink already exists at $IMG_DIR/JPG/$b.jpg"
							else
								ln -s "$sd/Output/JPG/$basefile.jpg" "$IMG_DIR/JPG/$b.jpg" 
							fi
							
							if [ -f "$IMG_DIR/JPG-Preview/$b.jpg" ] ; then
								echo "ALERT: symlink already exists at $IMG_DIR/JPG-Preview/$b.jpg"
							else
								ln -s "$sd/Output/JPG-Preview/$basefile.jpg" "$IMG_DIR/JPG-Preview/$b.jpg" 
							fi
							
							if [ -f "$IMG_DIR/JPG-Thumbnail/$b.jpg" ] ; then
								echo "ALERT: symlink already exists at $IMG_DIR/JPG-Thumbnail/$b.jpg"
							else
								ln -s "$sd/Output/JPG-Thumbnail/$basefile.jpg" "$IMG_DIR/JPG-Thumbnail/$b.jpg" 
							fi
							
							if [ -f "$IMG_DIR/DNG/$b.dng" ] ; then
								echo "ALERT: symlink already exists at $IMG_DIR/DNG/$b.dng"
							else
								ln -s "$sd/Output/DNG/$basefile.dng" "$IMG_DIR/DNG/$b.dng" 
							fi
							
							if [ -f "$IMG_DIR/RAW/$b.CR2" ] ; then
								echo "ALERT: symlink already exists at $IMG_DIR/RAW/$b.CR2"
							else
								ln -s "$sd/Capture/$basefile.CR2" "$IMG_DIR/RAW/$b.CR2" 
							fi																											

							php add_image_set.php "$BATCH_ID" "$sd" "$b"
							
							if [ $? != 0 ] ; then
								echo "ALERT: Ingest failed for barcode $b in dir $sd"
								continue
							fi							

						done	
					done
									
					touch $sd/ingest_done # so we don't reprocess this directory
					
					echo "Done ingesting $sd"
				else
					echo "ALERT: Skipping badly formatted directory $sd"
				fi
				
			done
		fi
	done
done