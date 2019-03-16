#!/bin/bash

# Make sure that the filesystem is mounted correctly with mfsymlinks (CIFS only?)
content=$(cat test.txt)
if [ "Symlinks working!" != "$content" ];then
    echo "ERROR: huhimagestorage/ is not mounted with mfsymlinks"
    exit 1
fi

# run the sync - excluding CR2 and DNG for now
aws s3 sync --exclude "*.dng" --exclude "*.CR2" /mnt/huhimagestorage/huhspecimenimages/ s3://huhspecimenimages/