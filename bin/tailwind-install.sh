#!/usr/bin/env bash
#
# Fetch the pinned Tailwind CSS standalone binary into tools/tailwindcss.
#
#   bash bin/tailwind-install.sh          # install if missing or wrong version
#   bash bin/tailwind-install.sh --force  # re-download regardless
#
# The binary is ~107 MB and is gitignored. Every machine fetches its own copy;
# this script is the record of which version that is. There is no Node, no npm
# and no lockfile anywhere in this project, so the pin below IS the lockfile --
# change it deliberately, and note the change in docs/technical-add.md.

set -euo pipefail

TAILWIND_VERSION="v4.3.3"

BP="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEST="$BP/tools/tailwindcss"
BASE_URL="https://github.com/tailwindlabs/tailwindcss/releases/download/${TAILWIND_VERSION}"

force=0
[[ "${1:-}" == "--force" ]] && force=1

# --- pick the right build for this machine ------------------------------------

case "$(uname -s)" in
    Linux)  os="linux" ;;
    Darwin) os="macos" ;;
    *)      echo "error: unsupported OS $(uname -s)" >&2; exit 1 ;;
esac

case "$(uname -m)" in
    x86_64|amd64)  arch="x64" ;;
    aarch64|arm64) arch="arm64" ;;
    *)             echo "error: unsupported architecture $(uname -m)" >&2; exit 1 ;;
esac

# Alpine and other musl distros need the musl build; Ubuntu/WSL does not.
suffix=""
if [[ "$os" == "linux" ]] && ldd --version 2>&1 | head -1 | grep -qi musl; then
    suffix="-musl"
fi

asset="tailwindcss-${os}-${arch}${suffix}"

# --- skip if the pinned version is already in place ---------------------------

if [[ -x "$DEST" && $force -eq 0 ]]; then
    current="$("$DEST" --help 2>&1 | grep -oE 'v[0-9]+\.[0-9]+\.[0-9]+' | head -1 || true)"
    if [[ "$current" == "$TAILWIND_VERSION" ]]; then
        echo "Tailwind ${TAILWIND_VERSION} already installed at tools/tailwindcss"
        exit 0
    fi
    echo "Replacing ${current:-unknown version} with ${TAILWIND_VERSION}"
fi

# --- download, verify, install ------------------------------------------------

mkdir -p "$BP/tools"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

echo "Downloading ${asset} (${TAILWIND_VERSION}, ~107 MB)..."
curl -fL --progress-bar -o "$tmp/$asset" "${BASE_URL}/${asset}"

echo "Verifying checksum..."
curl -fsSL -o "$tmp/sha256sums.txt" "${BASE_URL}/sha256sums.txt"
# Names in sha256sums.txt carry a leading "./", so match with or without it.
expected="$(grep -E "[ *](\./)?${asset}\$" "$tmp/sha256sums.txt" | awk '{print $1}')"
actual="$(sha256sum "$tmp/$asset" | awk '{print $1}')"

if [[ -z "$expected" ]]; then
    echo "error: ${asset} not listed in sha256sums.txt" >&2
    exit 1
fi
if [[ "$expected" != "$actual" ]]; then
    echo "error: checksum mismatch for ${asset}" >&2
    echo "  expected ${expected}" >&2
    echo "  actual   ${actual}" >&2
    exit 1
fi
echo "  sha256 ${actual}  OK"

install -m 0755 "$tmp/$asset" "$DEST"
echo "Installed $("$DEST" --help 2>&1 | grep -oE '≈ tailwindcss v[0-9.]+' | head -1 || echo "$TAILWIND_VERSION") -> tools/tailwindcss"
