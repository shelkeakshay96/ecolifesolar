# Vendored libraries

There is no Composer in this project and therefore **no lockfile**. This file is
the record instead: if it is not written down here, nobody will know what version
is installed or where it came from.

| Library | Version | Location | Source |
|---|---|---|---|
| PHPMailer | see `lib/internal/PHPMailer/VERSION` | `lib/internal/PHPMailer/src/` | https://github.com/PHPMailer/PHPMailer |

Only the `src/*.php` files and `LICENSE` are copied. Tests, examples and the
Composer manifest are not — they are not used and would be dead weight in the
repository.

## Why it is vendored rather than installed

The application has no Node and no Composer by design, so that deploying is a
file copy and nothing on the server has to resolve dependencies at deploy time.
The cost is this file, and the update procedure below.

## Updating

```bash
TAG=v7.1.1   # or whatever the current release is
curl -fsSL -o /tmp/phpmailer.tar.gz \
  "https://github.com/PHPMailer/PHPMailer/archive/refs/tags/${TAG}.tar.gz"
tar -xzf /tmp/phpmailer.tar.gz -C /tmp
cp /tmp/PHPMailer-${TAG#v}/src/*.php lib/internal/PHPMailer/src/
cp /tmp/PHPMailer-${TAG#v}/LICENSE   lib/internal/PHPMailer/
echo "$TAG" > lib/internal/PHPMailer/VERSION
```

Then send a test message and confirm it still works:

```bash
bin/ecolife mail:test you@example.com
```

## Watch for security advisories

PHPMailer has had remote code execution advisories in the past. Nothing here
checks for updates automatically, so subscribe to releases on the GitHub
repository — an unwatched vendored dependency is the main hazard of this
approach, and it is a real one.
