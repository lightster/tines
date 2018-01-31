install:
	docker-compose build
	docker-compose run --rm php composer install
