#!/bin/bash -e

# Runs in the app container after its installation (ads.json, postContainerInstallDockerScript, `ads rise`):
# installs what is needed to develop, test and release Papdy (composer build).

appPath=
source /prepare-parameters.sh

if [[ -z "${appPath}" ]]; then
  >&2 echo "No app path specified!"
  exit 1
fi

# PHP extensions required by composer.json (gmp) and by Composer itself (mbstring, zip)
cosyses PHP 8.3 extension.sh --extensions gmp,mbstring,zip

cosyses Composer 2.9

# Release tools used by the composer scripts phar and bin, dpkg-deb for deb is part of the image
cosyses Box 4.7
cosyses Phpacker 0.6

# Project dependencies, installed as the owner of the mounted project directory
appUser=$(stat -c %U "${appPath}")
run-as-user "${appUser}" "cd ${appPath} && composer install --ansi --no-interaction"
