¡Bienvenidos a mi prueba técnica para Widitrade!

La prueba está hecha intentando utilizar las últimas tecnologías disponibles, en este caso para la prueba se ha utilizado:

- Docker
- Symfony 7.1
- Php 8.3.12
- Postgres 17

Para las pruebas de API al ser una app exclusivamente de Back se ha utilizado Postman para las Request.

Para la autenticación mediante JWT hay que crear la clave pública y privada en src/config/jwt, para esto podemos utilizar el siguiente comando:

- php bin/console lexik:jwt:generate-keypair

Para la DB podéis generar la migración gracias a la Entity mediante el comando:

- php bin/console doctrine:migrations:diff
- php bin/console doctrine:migrations:migrate
- php bin/console doctrine:schema:validate (Este ya es opcional, pero simplemente es para verificar que las entidades estén alineadas con la DB)

Esto os lo dejo así, ya que yo al realizar el proyecto en Windows en lugar de MacOs como estoy más acostumbrado he tenido algún problema de comandos y creo que sería preferible generar la migración para poder ahorraros todos los errores posibles.
Pero en caso de que necesitéis que os pase las migraciones me lo decís e intentaré pasároslas para que no tengáis ningún problema.

Culquier duda que tengáis a la hora de hacer funcionar el proyecto ya que he visto que trabajáis con MacOS (Yo también he trabajado simpre con Mac), pero como esto lo he hecho con mi PC personal que es Windows si hubiese cualquier incomatibilidad o problema podemos comunicarnos para tratar de solucionarlo.

También cualquier sugerencia para mejorar el proyecto ya que hasta a pesar de trabajar con PHP nunca había trabajado con Symfony y al tener tantas cosas propias estoy convencido de que se podrán mejorar muchas cosas, o cualquier cosa del enunciado si por algún motivo no he entendido bien o realizado como vosotros pedíais, no dudéis en decirmelo :D

¡Muchas gracias!
