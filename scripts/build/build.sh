#!/bin/bash -e

currentPath="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

name=
buildPath=
buildUser=
buildType=
buildUrl=
buildProject=
buildProjectUser=
buildProjectPassword=
buildSharedPath=
buildLink=
buildEnv=
buildMemoryLimit=
phpExecutable=
composerExecutable=

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

if [[ -z "${buildSharedPath}" ]]; then
  buildSharedPath="shared"
fi

if [[ -z "${phpExecutable}" ]]; then
  set +e
  phpExecutable="$(which php 2>/dev/null)"
  set -e
  if [[ -z "${phpExecutable}" ]]; then
    >&2 echo "No PHP executable found"
    exit 1
  fi
fi

if [[ -z "${composerExecutable}" ]]; then
  set +e
  composerExecutable="$(which composer 2>/dev/null)"
  set -e
  if [[ -z "${composerExecutable}" ]]; then
    >&2 echo "No Composer executable found"
    exit 1
  fi
fi

currentUser=$(whoami)
if [[ -z "${buildUser}" ]]; then
  buildUser="${currentUser}"
fi

if [[ ! -d "${buildPath}" ]]; then
  set +e
  if [[ "${buildUser}" != "${currentUser}" ]]; then
    echo "Creating base build path: ${buildPath} with user: ${buildUser}"
    if ! sudo -H -u "${buildUser}" bash -c "mkdir -p ${buildPath} 2>/dev/null"; then
      sudo -H -u "${buildUser}" bash -c "sudo mkdir -p ${buildPath} 2>/dev/null"
      sudo -H -u "${buildUser}" bash -c "sudo chown ${buildUser} ${buildPath} 2>/dev/null"
    else
      sudo -H -u "${buildUser}" bash -c "chown ${currentUser} ${buildPath} 2>/dev/null"
    fi
  else
    echo "Creating base build path: ${buildPath}"
    if ! mkdir -p "${buildPath}" 2>/dev/null; then
      sudo mkdir -p "${buildPath}" 2>/dev/null
      sudo chown "${currentUser}" "${buildPath}" 2>/dev/null
    fi
  fi
  set -e
fi

if [[ ! -d "${buildPath}" ]]; then
  >&2 echo "Could not create build path: ${buildPath}"
  exit 1
fi

# shellcheck disable=SC2001
buildName=$(echo "${name}" | sed 's/[^a-zA-Z0-9\.\-]/_/g')

buildNamePath="${buildPath}/${buildName}"

if [[ -d "${buildNamePath}" ]]; then
  if [[ "${buildUser}" != "${currentUser}" ]]; then
    echo "Removing previous build path: ${buildNamePath} with user: ${buildUser}"
    sudo -H -u "${buildUser}" bash -c "rm -rf ${buildNamePath}/"
  else
    echo "Removing previous build path: ${buildNamePath}"
    rm -rf "${buildNamePath:?}/"
  fi
fi

if [[ "${buildType}" == "git" ]]; then
  if [[ $(which ssh-keyscan >/dev/null 2>&1 && echo "yes" || echo "no") == "yes" ]]; then
    buildUserHome=$(grep "${buildUser}:" /etc/passwd | cut -d':' -f6)

    echo "Checking SSH keys of repository: ${buildUrl}"
    if [[ ! -f "${buildUserHome}/.ssh/known_hosts" ]]; then
      if [[ "${buildUser}" != "${currentUser}" ]]; then
        sudo -H -u "${buildUser}" bash -c "touch ${buildUserHome}/.ssh/known_hosts"
      else
        touch "${buildUserHome}/.ssh/known_hosts"
      fi
    fi

    gitHosts=( "bitbucket.org" "github.com" "gitlab.com" )

    for gitHost in "${gitHosts[@]}"; do
      if [[ $(echo "${buildUrl}" | grep "${gitHost}" | wc -l) -gt 0 ]]; then
        echo "Checking ${gitHost} SSH keys"
        set -f
        IFS=$'\n';
        #keys=( $(set -f; IFS=$'\n'; ssh-keyscan -H rsa "${gitHost}" 2>/dev/null) )
        keys=()
        set +f
        unset IFS
        echo "Found ${#keys[@]} key(s)"
        for key in "${keys[@]}"; do
          pregFindKey=$(echo "${key}" | awk '{print $3}')
          if [[ "${buildUser}" != "${currentUser}" ]]; then
            if [[ $(sudo -H -u "${buildUser}" bash -c "grep \"${pregFindKey}\" ${buildUserHome}/.ssh/known_hosts" | wc -l) -eq 0 ]]; then
              echo "Adding known host: ${gitHost} with user: ${buildUser}"
              sudo -H -u "${buildUser}" bash -c "echo \"${key}\" >> ${buildUserHome}/.ssh/known_hosts"
            fi
          else
            if [[ $(grep "${pregFindKey}" "${buildUserHome}/.ssh/known_hosts" | wc -l) -eq 0 ]]; then
              echo "Adding known host: ${gitHost}"
              echo "${key}" >> "${buildUserHome}/.ssh/known_hosts"
            fi
          fi
        done
      fi
    done
  fi

  if [[ "${buildUser}" != "${currentUser}" ]]; then
    echo "Checking if branch, tag or pull requests exists for name: ${name} in repository: ${buildUrl} with user: ${buildUser}"
    if [[ $(sudo -H -u "${buildUser}" bash -c "git ls-remote --heads \"${buildUrl}\" \"${name}\"" | wc -l) -eq 0 ]]; then
      echo "Branch ${name} does not exist in repository: ${buildUrl}"
      if [[ $(sudo -H -u "${buildUser}" bash -c "git ls-remote --tags \"${buildUrl}\" \"${name}\"" | wc -l) -eq 0 ]]; then
        echo "Tag ${name} does not exist in repository: ${buildUrl}"
        if [[ $(sudo -H -u "${buildUser}" bash -c "git ls-remote --refs \"${buildUrl}\" \"refs/pull/${name}/head\"" | wc -l) -eq 0 ]]; then
          >&2 echo "Pull request ${name} does not exist in repository: ${buildUrl}"
          exit 1
        else
          echo "Pull request ${name} exists in repository: ${buildUrl}"
          isPullRequest=1
        fi
      else
        echo "Tag ${name} exists in repository: ${buildUrl}"
        isTag=1
      fi
    else
      echo "Branch ${name} exists in repository: ${buildUrl}"
      isBranch=1
    fi
  else
    echo "Checking if branch, tag or pull requests exists for name: ${name} in repository: ${buildUrl}"
    if [[ $(git ls-remote --heads "${buildUrl}" "${name}" | wc -l) -eq 0 ]]; then
      echo "Branch ${name} does not exist in repository: ${buildUrl}"
      if [[ $(git ls-remote --tags "${buildUrl}" "${name}" | wc -l) -eq 0 ]]; then
        echo "Tag ${name} does not exist in repository: ${buildUrl}"
        if [[ $(git ls-remote --refs "${buildUrl}" "refs/pull/${name}/head" | wc -l) -eq 0 ]]; then
          >&2 echo "Pull request ${name} does not exist in repository: ${buildUrl}"
          exit 1
        else
          echo "Pull request ${name} exists in repository: ${buildUrl}"
          isPullRequest=1
        fi
      else
        echo "Tag ${name} exists in repository: ${buildUrl}"
        isTag=1
      fi
    else
      echo "Branch ${name} exists in repository: ${buildUrl}"
      isBranch=1
    fi
  fi

  buildGitPath="${buildPath}/git/${buildName}"

  if [[ -d "${buildGitPath}" ]]; then
    if [[ "${buildUser}" != "${currentUser}" ]]; then
      echo "Removing build GIT path: ${buildGitPath} with user: ${buildUser}"
      sudo -H -u "${buildUser}" bash -c "rm -rf ${buildGitPath}/"
    else
      echo "Removing build GIT path: ${buildGitPath}"
      rm -rf "${buildGitPath:?}/"
    fi
  fi

  if [[ "${buildUser}" != "${currentUser}" ]]; then
    echo "Creating build GIT path: ${buildGitPath} with user: ${buildUser}"
    sudo -H -u "${buildUser}" bash -c "mkdir -p ${buildGitPath}"
  else
    echo "Creating build GIT path: ${buildGitPath}"
    mkdir -p "${buildGitPath}"
  fi

  if [[ "${buildUser}" != "${currentUser}" ]]; then
    if [[ $(sudo -H -u "${buildUser}" bash -c "git config --global safe.directory" | grep "${buildGitPath}" | wc -l) -eq 0 ]]; then
      echo "Adding GIT safe directory: ${buildGitPath} with user: ${buildUser}"
      sudo -H -u "${buildUser}" bash -c "git config --global --add safe.directory \"${buildGitPath}\""
    fi
  else
    if [[ $(git config --global safe.directory | grep "${buildGitPath}" | wc -l) -eq 0 ]]; then
      echo "Adding GIT safe directory: ${buildGitPath}"
      git config --global --add safe.directory "${buildGitPath}"
    fi
  fi

  cd "${buildGitPath}"

  if [[ "${buildUser}" != "${currentUser}" ]]; then
    echo "Cloning repository into path: ${buildGitPath} with user: ${buildUser}"
    sudo -H -u "${buildUser}" bash -c "git clone ${buildUrl} ."
  else
    echo "Cloning repository into path: ${buildGitPath}"
    git clone "${buildUrl}" .
  fi

  if [[ "${buildUser}" != "${currentUser}" ]]; then
    currentRef=$(sudo -H -u "${buildUser}" bash -c "git rev-parse --abbrev-ref HEAD")
  else
    currentRef=$(git rev-parse --abbrev-ref HEAD)
  fi

  if [[ "${currentRef}" == "${name}" ]]; then
    echo "Already checked out: ${name}"
  else
    if [[ "${isBranch}" == 1 ]]; then
      if [[ "${buildUser}" != "${currentUser}" ]]; then
        echo "Checking out branch: ${name} with user: ${buildUser}"
        sudo -H -u "${buildUser}" bash -c "git checkout --track -b ${name} remotes/origin/${name}"
      else
        echo "Checking out branch: ${name}"
        git checkout --track -b "${name}" "remotes/origin/${name}"
      fi
    elif [[ "${isTag}" == 1 ]]; then
      if [[ "${buildUser}" != "${currentUser}" ]]; then
        echo "Checking out tag: ${name} with user: ${buildUser}"
        sudo -H -u "${buildUser}" bash -c "git checkout --no-track -b branch_${name} tags/${name}"
      else
        echo "Checking out tag: ${name}"
        git checkout --no-track -b "branch_${name}" "tags/${name}"
      fi
    elif [[ "${isPullRequest}" == 1 ]]; then
      if [[ "${buildUser}" != "${currentUser}" ]]; then
        echo "Checking out pull request: ${name} with user: ${buildUser}"
        sudo -H -u "${buildUser}" bash -c "git fetch origin pull/${name}/head:pr_${name}"
        sudo -H -u "${buildUser}" bash -c "git switch pr_${name}"
      else
        echo "Checking out pull request: ${name}"
        git fetch origin "pull/${name}/head:pr_${name}"
        git switch "pr_${name}"
      fi
    fi
  fi

  if [[ "${isBranch}" == 1 ]]; then
    if [[ "${buildUser}" != "${currentUser}" ]]; then
      echo "Pulling branch: ${name} with user: ${buildUser}"
      sudo -H -u "${buildUser}" bash -c "git pull"
    else
      echo "Pulling branch: ${name}"
      git pull
    fi
  fi

  if [[ -d .git ]]; then
    if [[ "${buildUser}" != "${currentUser}" ]]; then
      echo "Removing Git directory with user: ${buildUser}"
      sudo -H -u "${buildUser}" bash -c "rm -rf .git"
    else
      echo "Removing Git directory"
      rm -rf .git
    fi
  fi

  if [[ "${buildUser}" != "${currentUser}" ]]; then
    echo "Creating build path: ${buildNamePath} with user: ${buildUser}"
    sudo -H -u "${buildUser}" bash -c "mkdir -p ${buildNamePath}"
  else
    echo "Creating build path: ${buildNamePath}"
    mkdir -p "${buildNamePath}"
  fi

  shopt -s dotglob
  if [[ "${buildUser}" != "${currentUser}" ]]; then
    echo "Copying all files from GIT path: ${buildGitPath} to build path: ${buildNamePath} with user: ${buildUser}"
    sudo -H -u "${buildUser}" bash -c "cp -rf \"${buildGitPath}\"/* \"${buildNamePath}\"/"
  else
    echo "Copying all files from GIT path: ${buildGitPath} to build path: ${buildNamePath}"
    cp -rf "${buildGitPath}"/* "${buildNamePath}"/
  fi
  shopt -u dotglob
else
  if [[ -n "${buildProjectUser}" ]] && [[ -n "${buildProjectPassword}" ]]; then
    host=$(echo "${buildUrl}" | awk -F[/:] '{print $4}')
    if [[ "${buildUser}" != "${currentUser}" ]]; then
      echo "Setting project user: ${buildProjectUser} and password with user: ${buildUser}"
      sudo -H -u "${buildUser}" bash -c "composer config --global \"http-basic.${host}\" \"${buildProjectUser}\" \"${buildProjectPassword}\""
    else
      echo "Setting project user: ${buildProjectUser} and password"
      composer config --global "http-basic.${host}" "${buildProjectUser}" "${buildProjectPassword}"
    fi
  fi
  if [[ "${buildUser}" != "${currentUser}" ]]; then
    echo "Creating composer project from url: ${buildUrl} with name: ${buildProject}=${name} in path: ${buildNamePath} with user: ${buildUser}"
    sudo -H -u "${buildUser}" bash -c "composer create-project --ansi --prefer-dist \"--repository-url=${buildUrl}\" \"${buildProject}=${name}\" \"${buildNamePath}\""
  else
    echo "Creating composer project from url: ${buildUrl} with name: ${buildProject}=${name} in path: ${buildNamePath}"
    composer create-project --ansi --prefer-dist "--repository-url=${buildUrl}" "${buildProject}=${name}" "${buildNamePath}"
  fi
  cd "${buildPath}"
fi

if [[ -n "${buildLink}" ]]; then
  for nextBuildLink in "${buildLink[@]}"; do
    IFS=':' read -ra nextBuildLinkParts <<< "${nextBuildLink}"
    linkSource="${nextBuildLinkParts[0]}"
    linkTarget="${nextBuildLinkParts[1]}"
    if [[ "${linkSource}" == "${linkSource#/}" ]]; then
      linkSourcePath="${buildPath}/${buildSharedPath}/${linkSource}"
    else
      linkSourcePath="${linkSource}"
    fi
    if [[ ! -e "${linkSourcePath}" ]]; then
      >&2 echo "Link source does not exist: ${linkSourcePath}"
      exit 1
    fi
    if [[ "${linkTarget}" == "${linkTarget#/}" ]]; then
      linkTargetPath="${buildNamePath}/${linkTarget}"
    else
      linkTargetPath="${linkTarget}"
    fi
    if [[ -e "${linkTargetPath}" ]]; then
      >&2 echo "Link target does exist: ${linkTargetPath}"
      exit 1
    fi
    if [[ "${buildUser}" != "${currentUser}" ]]; then
      echo "Linking source: ${linkSourcePath} to target: ${linkTargetPath} with user: ${buildUser}"
      sudo -H -u "${buildUser}" bash -c "ln -s \"${linkSourcePath}\" \"${linkTargetPath}\""
    else
      echo "Linking source: ${linkSourcePath} to target: ${linkTargetPath}"
      ln -s "${linkSourcePath}" "${linkTargetPath}"
    fi
  done
fi

processEnvironmentVariables=()

if [[ -n "${buildEnv}" ]]; then
  for nextBuildEnv in "${buildEnv[@]}"; do
    IFS='=' read -ra nextBuildEnvParts <<< "${nextBuildEnv}"
    name="${nextBuildEnvParts[0]}"
    value="${nextBuildEnvParts[1]}"
    if [[ "${buildUser}" != "${currentUser}" ]]; then
      echo "Exporting environment variable ${name}=${value} with user: ${buildUser}"
      processEnvironmentVariables+=("${name}=${value}")
    else
      echo "Exporting environment variable ${name}=${value}"
      export "${name}=${value}"
    fi
  done
fi

cd "${buildNamePath}"

if [[ -f composer.json ]]; then
  if [[ -n "${buildMemoryLimit}" ]]; then
    echo "Using memory limit: ${buildMemoryLimit}"
    processEnvironmentVariables+=("COMPOSER_MEMORY_LIMIT=${buildMemoryLimit}")
  fi
  if [[ "${buildUser}" != "${currentUser}" ]]; then
    echo "Installing composer project in path: ${buildNamePath} with user: ${buildUser}"
    sudo -H -u "${buildUser}" bash -c "${processEnvironmentVariables[*]} ${phpExecutable} ${composerExecutable} install --ansi --prefer-dist"
  else
    echo "Installing composer project in path: ${buildNamePath}"
    "${phpExecutable}" "${composerExecutable}" install --ansi --prefer-dist
  fi
fi

if [[ -n "${buildLink}" ]]; then
  for nextBuildLink in "${buildLink[@]}"; do
    IFS=':' read -ra nextBuildLinkParts <<< "${nextBuildLink}"
    linkTarget="${nextBuildLinkParts[1]}"
    if [[ "${linkTarget}" == "${linkTarget#/}" ]]; then
      linkTargetPath="${buildNamePath}/${linkTarget}"
    else
      linkTargetPath="${linkTarget}"
    fi
    if [[ -e "${linkTargetPath}" ]]; then
      if [[ "${buildUser}" != "${currentUser}" ]]; then
        echo "Unlinking target: ${linkTargetPath} with user: ${buildUser}"
        sudo -H -u "${buildUser}" bash -c "rm \"${linkTargetPath}\""
      else
        echo "Unlinking target: ${linkTargetPath}"
        rm "${linkTargetPath}"
      fi
    fi
  done
fi

echo "Creating vcs-info.txt"
echo "Version: ${name}" > vcs-info.txt
echo "Build-Date: $(LC_ALL=en_US.utf8 date +"%Y-%m-%d %H:%M:%S %z")" >> vcs-info.txt

buildNameFile="${buildPath}/${buildName}.tar.gz"

if [[ -f "${buildNameFile}" ]]; then
  if [[ "${buildUser}" != "${currentUser}" ]]; then
    echo "Removing previous archive at: ${buildNameFile} with user: ${buildUser}"
    sudo -H -u "${buildUser}" bash -c "rm -rf ${buildNameFile}"
  else
    echo "Removing previous archive at: ${buildNameFile}"
    rm -rf "${buildNameFile}"
  fi
fi

if [[ "${buildUser}" != "${currentUser}" ]]; then
  echo "Creating archive of branch: ${buildNameFile} with user: ${buildUser}"
  sudo -H -u "${buildUser}" bash -c "tar -zcf ${buildNameFile} ."
else
  echo "Creating archive of branch: ${buildNameFile}"
  tar -zcf "${buildNameFile}" .
fi

cd ..

if [[ "${buildUser}" != "${currentUser}" ]]; then
  echo "Removing branch build path: ${buildName} with user: ${buildUser}"
  sudo -H -u "${buildUser}" bash -c "rm -rf ${buildName}"
else
  echo "Removing branch build path: ${buildName}"
  rm -rf "${buildName}"
fi
