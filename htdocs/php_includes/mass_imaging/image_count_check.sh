#!/bin/bash

BASEDIR="."

if [ "$1" != "" ]; then
    BASEDIR=$1
fi

echo "Counting CR2 files ..."
COUNT_CR2=$(ls -lh $BASEDIR/Capture/*.CR2 | wc -l)

echo "Counting JPG files ..."
COUNT_JPG=$(ls -lh $BASEDIR/Output/JPG/*.jpg | wc -l)

if [ $COUNT_CR2 -ne $COUNT_JPG ]
then
 echo Err: Problem with JPEGs: $COUNT_JPG doesnt match $COUNT_CR2
 exit 1
fi

echo "Counting JPG-Preview files ..."
COUNT_JPG_PREV=$(ls -lh $BASEDIR/Output/JPG-Preview/*.jpg | wc -l)

if [ $COUNT_CR2 -ne $COUNT_JPG_PREV ]
then
 echo Err: Problem with JPEG Previews: $COUNT_JPG_PREV doesnt match $COUNT_CR2
 exit 1
fi

echo "Counting JPG-Thumbnail files ..."
COUNT_JPG_THUMB=$(ls -lh $BASEDIR/Output/JPG-Thumbnail/*.jpg | wc -l)

if [ $COUNT_CR2 -ne $COUNT_JPG_THUMB ]
then
 echo Err: Problem with JPEG Thumbnails: $COUNT_JPG_THUMB doesnt match $COUNT_CR2
 exit 1
fi

echo "Counting DNG files ..."
COUNT_DNG=$(ls -lh $BASEDIR/Output/DNG/*.dng | wc -l)

if [ $COUNT_CR2 -ne $COUNT_DNG ]
then
 echo Err: Problem with DNGs: $COUNT_DNG doesnt match $COUNT_CR2
 exit 1
else
 echo OK: Derivative counts match!
fi


