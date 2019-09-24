#!/bin/bash

# For every JPG in batch, cache the barcode list produced by zbar
# first argument is number of threads to launch


if [ "$1" == "" ] || [ "$2" == "" ] ; then
        echo "USAGE: cache_barcodes.sh <dir> <numprocs>";
        exit 1;
fi

NUM_PROCS=$2;


find $1/Output/JPG/ -iname "*.jpg" -not -iname ".*" | while read f
do

echo "Caching $f"

    ./runcached.sh --ttl 86400 --cache-dir /tmp/zbarcache --ignore-env --ignore-pwd --prune-cache zbarimg -q --raw -Sdisable -Scode39.enable "$f" >/dev/null &

    while [ $(jobs -p | wc -l) -ge $NUM_PROCS ]; do
#       echo "Sleeping"
#       jobs
        sleep 1 ;
    done

done
