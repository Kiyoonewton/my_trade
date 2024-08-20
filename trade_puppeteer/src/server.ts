import { ApolloServer } from "@apollo/server";
import { startStandaloneServer } from "@apollo/server/standalone";
import { TypeDefs } from "./gql/schema.js";
import { resolvers } from "./gql/resolver.js";

const port = Number(process?.env?.PORT) || 4000;

const server = new ApolloServer({
  typeDefs: TypeDefs,
  resolvers,
});

const { url } = await startStandaloneServer(server, {
  listen: { port },
});

console.log(`🚀  Server ready at: ${url}`);
