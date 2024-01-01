# Crews

[![Uses XP Framework](https://raw.githubusercontent.com/xp-framework/web/master/static/xp-framework-badge.png)](https://github.com/xp-framework/core)
[![BSD Licence](https://raw.githubusercontent.com/xp-framework/web/master/static/licence-bsd.png)](https://github.com/xp-framework/core/blob/master/LICENCE.md)
[![Requires PHP 8.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-8_0plus.svg)](http://php.net/)

Lightweight asynchronous communication for teams.

## Configuration

```ini
[mongo]
connect=mongo://user:password@localhost/one
database=crews

[redis]
connect=redis://localhost

[oauth]
authorize=http://localhost:8443/oauth/common/authorize
token=http://localhost:8443/oauth/common/token
userinfo=http://localhost:8443/graph/me
client=client-id
secret=client-secret
scopes[]=user

[user]
handle="{{id}}"
first="{{givenName}}"
name="{{givenName}} {{surname}}"
mail="{{mail}}"
```