
#### optional - install phpMyAdmin
`docker run --name o_phpmyadmin -d -p 8080:80 --link o_db:db --network lpastas_jan_naruskevic_o_network -e PMA_HOST=db -e PMA_PORT=3306 -e PMA_USER=root -e PMA_PASSWORD=root phpmyadmin/phpmyadmin`

## Update hosts file

127.0.0.1 local.order-api.com
