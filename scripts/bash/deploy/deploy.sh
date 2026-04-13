#!/bin/bash -e

currentPath="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

name=
buildNameFile=
deployId=
deployPath=
deployUser=
deploySharedPath=
deployLink=
webPath=

if [[ -f prepare-parameters.sh ]]; then
  source prepare-parameters.sh
elif [[ -f /tmp/prepare-parameters.sh ]]; then
  source /tmp/prepare-parameters.sh
elif [[ -f "${currentPath}/../prepare-parameters.sh" ]]; then
  source "${currentPath}/../prepare-parameters.sh"
fi

if [[ -z "${name}" ]]; then
  >&2 echo "No name to deploy"
  exit 1
fi

if [[ -z "${buildNameFile}" ]]; then
  >&2 echo "No build name file to deploy"
  exit 1
fi

if [[ ! -f "${buildNameFile}" ]]; then
  >&2 echo "No build file found"
  exit 1
fi

if [[ -z "${deployId}" ]]; then
  >&2 echo "No deploy id"
  exit 1
fi

if [[ -z "${deployPath}" ]]; then
  >&2 echo "No deploy path"
  exit 1
fi

if [[ -z "${deploySharedPath}" ]]; then
  deploySharedPath="shared"
fi

if [[ -z "${webPath}" ]]; then
  webPath="${deployPath}/current"
fi
if [[ "${webPath}" == "${webPath#/}" ]]; then
  webPath="${deployPath}/${webPath}"
fi

currentUser=$(whoami)
if [[ -z "${deployUser}" ]]; then
  deployUser="${currentUser}"
fi

deployIdPath="${deployPath}/${deployId}"

if [[ -d "${deployIdPath}" ]]; then
  set +e
  if [[ "${deployUser}" != "${currentUser}" ]]; then
    echo "Removing previous deploy path: ${deployIdPath} with user: ${deployUser}"
    if ! sudo -H -u "${deployUser}" bash -c "rm -rf ${deployIdPath} 2>/dev/null"; then
      sudo -H -u "${deployUser}" bash -c "sudo rm -rf ${deployIdPath} 2>/dev/null"
    fi
  else
    echo "Removing previous deploy path: ${deployIdPath}"
    if ! rm -rf "${deployIdPath}" 2>/dev/null; then
      sudo rm -rf "${deployIdPath}" 2>/dev/null
    fi
  fi
  set -e
fi

if [[ ! -d "${deployIdPath}" ]]; then
  set +e
  if [[ "${deployUser}" != "${currentUser}" ]]; then
    echo "Creating deploy path: ${deployIdPath} with user: ${deployUser}"
    if ! sudo -H -u "${deployUser}" bash -c "mkdir -p ${deployIdPath} 2>/dev/null"; then
      sudo -H -u "${deployUser}" bash -c "sudo mkdir -p ${deployIdPath} 2>/dev/null"
      sudo -H -u "${deployUser}" bash -c "sudo chown ${deployUser}: ${deployIdPath} 2>/dev/null"
    else
      sudo -H -u "${deployUser}" bash -c "chown ${currentUser}: ${deployIdPath} 2>/dev/null"
    fi
  else
    echo "Creating deploy path: ${deployIdPath}"
    if ! mkdir -p "${deployIdPath}" 2>/dev/null; then
      sudo mkdir -p "${deployIdPath}" 2>/dev/null
      sudo chown "${currentUser}": "${deployIdPath}" 2>/dev/null
    fi
  fi
  set -e
fi

if [[ ! -d "${deployIdPath}" ]]; then
  >&2 echo "Could not create deploy path: ${deployIdPath}"
  exit 1
fi

echo "Copy build archive from: ${buildNameFile} to deploy path: ${deployIdPath}"
if [[ "${deployUser}" != "${currentUser}" ]]; then
  sudo -H -u "${deployUser}" bash -c "cp ${buildNameFile} ${deployIdPath}"
else
  cp "${buildNameFile}" "${deployIdPath}"
fi

fileName=$(basename "${buildNameFile}")

cd "${deployIdPath}"

echo "Extracting build archive: ${fileName}"
if [[ "${deployUser}" != "${currentUser}" ]]; then
  sudo -H -u "${deployUser}" bash -c "tar -xf ${fileName} | cat"
else
  tar -xf "${fileName}" | cat
fi

echo "Removing copied build archive: ${fileName}"
if [[ "${deployUser}" != "${currentUser}" ]]; then
  sudo -H -u "${deployUser}" bash -c "rm -rf ${fileName}"
else
  rm -rf "${fileName}"
fi

if [[ -n "${deployLink}" ]]; then
  for nextDeployLink in "${deployLink[@]}"; do
    IFS=':' read -ra nextBuildLinkParts <<< "${nextDeployLink}"
    linkSource="${nextBuildLinkParts[0]}"
    linkTarget="${nextBuildLinkParts[1]}"
    linkMode="${nextBuildLinkParts[2]}"
    if [[ "${linkSource}" == "${linkSource#/}" ]]; then
      if [[ "${deploySharedPath}" == "${deploySharedPath#/}" ]]; then
        linkSourcePath="${deployPath}/${deploySharedPath}/${linkSource}"
      else
        linkSourcePath="${deploySharedPath}/${linkSource}"
      fi
    else
      linkSourcePath="${linkSource}"
    fi
    if [[ ! -e "${linkSourcePath}" ]]; then
      >&2 echo "Link source does not exist: ${linkSourcePath}"
      exit 1
    fi
    if [[ "${linkTarget}" == "${linkTarget#/}" ]]; then
      linkTargetPath="${deployIdPath}/${linkTarget}"
    else
      linkTargetPath="${linkTarget}"
    fi
    if [[ -e "${linkTargetPath}" ]]; then
      if [[ "${linkMode}" == "f" ]]; then
        if [[ "${deployUser}" != "${currentUser}" ]]; then
          echo "Removing previous link target: ${linkTargetPath} with user: ${deployUser}"
          sudo -H -u "${deployUser}" bash -c "rm -rf \"${linkTargetPath}\""
        else
          echo "Removing previous link target: ${linkTargetPath}"
          rm -rf "${linkTargetPath}"
        fi
      else
        >&2 echo "Link target does exist: ${linkTargetPath}"
        exit 1
      fi
    fi
    linkTargetDirectory=$(dirname "${linkTargetPath}")
    if [[ ! -d "${linkTargetDirectory}" ]]; then
      set +e
      if [[ "${deployUser}" != "${currentUser}" ]]; then
        echo "Creating link target path: ${linkTargetDirectory} with user: ${deployUser}"
        if ! sudo -H -u "${deployUser}" bash -c "mkdir -p ${linkTargetDirectory} 2>/dev/null"; then
          sudo -H -u "${deployUser}" bash -c "sudo mkdir -p ${linkTargetDirectory} 2>/dev/null"
          sudo -H -u "${deployUser}" bash -c "sudo chown ${deployUser}: ${linkTargetDirectory} 2>/dev/null"
        else
          sudo -H -u "${deployUser}" bash -c "chown ${currentUser}: ${linkTargetDirectory} 2>/dev/null"
        fi
      else
        echo "Creating link target path: ${linkTargetDirectory}"
        if ! mkdir -p "${linkTargetDirectory}" 2>/dev/null; then
          sudo mkdir -p "${linkTargetDirectory}" 2>/dev/null
          sudo chown "${currentUser}": "${linkTargetDirectory}" 2>/dev/null
        fi
      fi
      set -e
    fi
    if [[ "${deployUser}" != "${currentUser}" ]]; then
      echo "Linking source: ${linkSourcePath} to target: ${linkTargetPath} with user: ${deployUser}"
      sudo -H -u "${deployUser}" bash -c "ln -sf \"${linkSourcePath}\" \"${linkTargetPath}\""
    else
      echo "Linking source: ${linkSourcePath} to target: ${linkTargetPath}"
      ln -sf "${linkSourcePath}" "${linkTargetPath}"
    fi
  done
fi

if [[ -L "${webPath}" ]]; then
  if [[ "${deployUser}" != "${currentUser}" ]]; then
    echo "Removing previous web path link: ${webPath} with user: ${deployUser}"
    sudo -H -u "${deployUser}" bash -c "rm \"${webPath}\""
  else
    echo "Removing previous web path link: ${webPath}"
    rm "${webPath}"
  fi
fi

if [[ -d "${webPath}" ]]; then
  if [[ "${deployUser}" != "${currentUser}" ]]; then
    echo "Moving previous web path: ${webPath} with user: ${deployUser}"
    sudo -H -u "${deployUser}" bash -c "mv \"${webPath}\" \"${webPath}_old\""
  else
    echo "Moving previous web path: ${linkTargetPath}"
    mv "${webPath}" "${webPath}_old"
  fi
fi

if [[ "${deployUser}" != "${currentUser}" ]]; then
  echo "Linking release from: ${deployIdPath} to: ${webPath} with user: ${deployUser}"
  sudo -H -u "${deployUser}" bash -c "ln -sf \"${deployIdPath}\" \"${webPath}\""
else
  echo "Linking release from: ${deployIdPath} to: ${webPath}"
  ln -sf "${deployIdPath}" "${webPath}"
fi
