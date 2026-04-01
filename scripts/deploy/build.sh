#!/bin/bash -e

currentPath="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

name=
buildPath=

if [[ -f prepare-parameters.sh ]]; then
  source prepare-parameters.sh
elif [[ -f /tmp/prepare-parameters.sh ]]; then
  source /tmp/prepare-parameters.sh
elif [[ -f "${currentPath}/../prepare-parameters.sh" ]]; then
  source "${currentPath}/../prepare-parameters.sh"
fi

if [[ -z "${name}" ]]; then
  >&2 echo "No name to build"
  exit 1
fi

# shellcheck disable=SC2001
buildName=$(echo "${name}" | sed 's/[^a-zA-Z0-9\.\-]/_/g')

buildNameFile="${buildPath}/${buildName}.tar.gz"

if [[ -f "${buildNameFile}" ]]; then
  echo "${buildNameFile}"
else
  >&2 echo "Build not found at: ${buildNameFile}"
  exit 1
fi
