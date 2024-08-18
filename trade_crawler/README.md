# trade_crawler


# to create a mongodb user 
docker exec -it crawler_db mongosh
db.createUser({
  user: "admin",
  pwd: "password123",
  roles: [ { role: "userAdminAnyDatabase", db: "admin" }, {role: "readWrite", db: "my_trade"} ]
})

