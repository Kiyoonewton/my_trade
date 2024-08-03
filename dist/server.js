import { ApolloServer } from "@apollo/server";
import { startStandaloneServer } from "@apollo/server/standalone";
import { TypeDefs } from "./gql/schema.js";
import { resolvers } from "./gql/resolver.js";
const server = new ApolloServer({
    typeDefs: TypeDefs,
    resolvers,
});
const { url } = await startStandaloneServer(server, {
    listen: { port: 4000 },
});
console.log(`🚀  Server ready at: ${url}`);
