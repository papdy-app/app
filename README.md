# PHP Application Deployment

This is simple application to build and deploy a PHP application on multiple servers which are not containerized.

## Installation

### Debian

**Latest:**

```bash
cd /tmp && curl -sLO https://raw.githubusercontent.com/papdy-app/app/refs/heads/master/build/linux/papdy.deb && sudo dpkg -i papdy.deb
```

or

```bash
curl -sLO https://raw.githubusercontent.com/papdy-app/app/refs/heads/master/build/linux/linux-x64 && chmod +x linux-x64 && mv linux-x64 papdy
```

**Specific version:**
```bash
cd /tmp && curl -sLO https://raw.githubusercontent.com/papdy-app/app/refs/tags/1.0.0/build/linux/papdy.deb && sudo dpkg -i papdy.deb
```

or

```bash
curl -sLO https://raw.githubusercontent.com/papdy-app/app/refs/tags/1.0.0/build/linux/linux-x64 && chmod +x linux-x64 && mv linux-x64 papdy
```

## Development

### Phar ###

**Install**
```bash
composer global require humbug/box
```
**Compile**
```bash
~/.config/composer/vendor/bin/box compile
```

### Binary ###

**Install**
```bash
composer global require phpacker/phpacker
```
**Compile**
```bash
~/.config/composer/vendor/bin/phpacker build all --src=./build/papdy.phar --dest=./build/
```

### Debian package ###

**Compile**
```bash
cd build/linux && mkdir -p debian/usr/bin && cp linux-x64 debian/usr/bin/papdy && dpkg-deb --build debian papdy.deb && rm -rf debian/usr && cd ../..
```
