#!/bin/bash
set -e

name="$first"
version="$second"
sourceDir="$third"
deployDir="$fourth"

if [-z "$name"] || [-z "$version"] || [-z "$sourceDir"] || [-z "$deployDir"]; then
    echo "Usage: ./buildBundle.sh <name> <version> <sourceDir> <deployDir>"
    exit 1
fi

if [! -d "$sourceDir"]; then
    echo "The source directory is not found: $sourceDir"
    exit 1
fi

versionDir="$deployDir/$name/$version"
stagingDir=$(mktemp -d)
trap 'rm -rf "$stagingDir"' EXIT

mkdir -p "$versionDir"
mkdir -p "$stagingDir/package"
cp -r "$sourceDir"/* "$stagingDir/package"/

echo "$version" > "$stagingDir/version.txt"
echo "new" > "$versionDir/status.txt"

tar -czf "$versionDir/bundle.tar.gz" -C "$stagingDir" .
echo "Bundle has been created at $versionDir/bundle.tar.gz"