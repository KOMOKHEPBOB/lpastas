## Update hosts file

```
127.0.0.1 local.order-api.com
```


# Project setup

```shell
cd docker \
    && docker compose up -d --build \
    && docker container exec -it o_php /bin/bash
```

#### Switch to www-data user
```
su -s /bin/bash www-data
```

#### Prepare database
```
bin/console doctrine:schema:drop --force \
    && bin/console doctrine:schema:update --force \
    && bin/console doctrine:fixtures:load -n \
    && bin/console messenger:setup-transports
```

#### optional - install phpMyAdmin
```shell
docker run --name o_phpmyadmin -d -p 8080:80 --link o_db:db --network lpastas_jan_naruskevic_o_network -e PMA_HOST=db -e PMA_PORT=3306 -e PMA_USER=root -e PMA_PASSWORD=root phpmyadmin/phpmyadmin
```

# Run tests

#### Prepare test database
```
bin/console doctrine:database:create --env=test \
    && bin/console doctrine:schema:drop --env=test --force \
    && bin/console doctrine:schema:update --env=test --force
```

```
bin/phpunit --testdox --testsuite=All --group=OrderApi
```
