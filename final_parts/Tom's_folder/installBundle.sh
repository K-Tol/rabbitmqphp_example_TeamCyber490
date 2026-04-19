#!/usr/bin/bash

bundlePath="$1"
targetPath="$2"

mkdir -p "$targetPath"
rm -rf "${targetPath:?}/"*
tar -xf "$bundlePath" -C "$targetPath"