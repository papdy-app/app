#!/bin/bash -e

if [[ $(declare -g >/dev/null 2>&1 && echo "true" || echo "false") == "true" ]]; then
  declare -Ag parameters
else
  declare -A parameters
fi

unparsedParameters=( )
while [[ "$#" -gt 0 ]]; do
  parameter="${1}"
  shift
  if [[ "${parameter:0:2}" == "--" ]] || [[ "${parameter}" =~ ^-[[:alpha:]][[:space:]]+ ]] || [[ "${parameter}" =~ ^-\?$ ]]; then
    if [[ "${parameter}" =~ ^--[[:alpha:]]+[[:space:]]+ ]]; then
      parameter="${parameter:2}"
      #key=$(echo "${parameter}" | grep -oP '[[:alpha:]]+(?=\s)' | tr -d "\n")
      key=$(echo "${parameter}" | grep -oE '^[[:alpha:]]+' | tr -d "\n")
      if [[ -n "${key}" ]]; then
        value=$(echo "${parameter:${#key}}" | xargs)
        # shellcheck disable=SC2034
        parameters["${key}"]="${value}"
        #>&2 echo eval "${key}=\"${value}\""
        eval "${key}=\"${value}\""
      fi
      continue
    fi
    if [[ "${parameter:0:2}" == "--" ]]; then
      key="${parameter:2}"
    elif [[ "${parameter}" =~ ^-\?$ ]]; then
      key="help"
    else
      key="${parameter:1}"
    fi
    if [[ "$#" -eq 0 ]]; then
      parameters["${key}"]=1
      #>&2 echo eval "${key}=1"
      eval "${key}=1"
    else
      value="${1}"
      if [[ "${value:0:2}" == "--" ]]; then
        parameters["${key}"]=1
        #>&2 echo eval "${key}=1"
        eval "${key}=1"
        continue
      fi
      shift
      if [[ -n "${key+set}" ]] && [[ -n "${!key}" ]]; then
        if [[ ! "$(declare -p "${key}")" =~ "declare -a" ]]; then
          #>&2 echo eval "${key}=( \"${!key}\" )"
          eval "${key}=( \"${!key}\" )"
        fi
        #>&2 echo eval "${key}+=(\"${value}\")"
        eval "${key}+=(\"${value}\")"
      else
        # shellcheck disable=SC2034
        parameters["${key}"]="${value}"
        #>&2 echo eval "${key}=\"${value}\""
        eval "${key}=\"${value}\""
      fi
    fi
  else
    unparsedParameters+=("${parameter}")
  fi
done
set -- "${unparsedParameters[@]}"

if test "${parameters["help"]+isset}" || test "${parameters["?"]+isset}"; then
  if [[ $(declare -F "usage" | wc -l) -gt 0 ]]; then
    usage
  else
    echo "No options available"
  fi
  exit 0
fi
