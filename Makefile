install:
	docker-compose build
	docker-compose run --rm php composer install

test:
	docker-compose run --rm php composer test
