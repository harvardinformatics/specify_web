#!/bin/bash

echo "Starting sync : $(date)"
# Make sure that the filesystem is mounted correctly with mfsymlinks (CIFS only?)
content=$(cat /mnt/huhimagestorage/huhspecimenimages/symlink_testfile.txt)
if [ "Symlinks working!" != "$content" ];then
    echo "ERROR: huhimagestorage/ is not mounted with mfsymlinks"
    exit 1
fi

# run the sync
echo "Starting sync of JPG-Thumbnail/ : $(date)"
aws s3 sync --quiet --size-only /mnt/huhimagestorage/huhspecimenimages/JPG-Thumbnail/ s3://huhspecimenimages/JPG-Thumbnail/
echo "Finished JPG-Thumbnail/ : $(date)"

echo "Starting sync of JPG-Preview/ : $(date)"
aws s3 sync --quiet --size-only /mnt/huhimagestorage/huhspecimenimages/JPG-Preview/ s3://huhspecimenimages/JPG-Preview/
echo "Finished JPG-Preview/ : $(date)"

echo "Starting sync of JPG/ : $(date)"
aws s3 sync --quiet --size-only /mnt/huhimagestorage/huhspecimenimages/JPG/ s3://huhspecimenimages/JPG/
echo "Finished JPG/ : $(date)"

echo "Starting sync of DNG/ : $(date)"
aws s3 sync --quiet --size-only /mnt/huhimagestorage/huhspecimenimages/DNG/ s3://huhspecimenimages/DNG/
echo "Finished DNG/ : $(date)"

#echo "Starting sync of RAW : $(date)"
#aws s3 sync --quiet --size-only /mnt/huhimagestorage/huhspecimenimages/RAW s3://huhspecimenimages/
#echo "Finished RAW/ : $(date)"

echo "Finished sync : $(date)"
