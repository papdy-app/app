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

### CS-Fixer ###
```bash
composer fix
```

### PHPStan ###
```bash
composer ps
```

### Phar ###

**Install**
```bash
composer global require humbug/box
```
**Compile**
```bash
composer phar
```

### Binary ###

**Install**
```bash
composer global require phpacker/phpacker
```
**Compile**
```bash
composer bin
```

### Debian package ###

**Compile**
```bash
composer deb
```
