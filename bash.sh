#!/bin/bash
NODB=${NODB:-0} # Whether or not to use a database

if [ "$NODB" == "1" ]; then
  echo "Do not use a database"
fi
