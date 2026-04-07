import { generateRobots } from "onedocs/seo";

const baseUrl = "https://rudel.dev";

export default function robots() {
  return generateRobots({ baseUrl });
}
