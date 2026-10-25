import { basename, dirname, extname, join, normalize, relative, resolve, sep } from "node:path";
import * as Console from "effect/Console";
import * as Effect from "effect/Effect";
import * as FileSystem from "effect/FileSystem";
import * as Flag from "effect/cli/Flag";
import { fail, main, script } from "./lib/cli.ts";

export interface DocumentationFiles {
    readonly read: (path: string) => string;
    readonly exists: (path: string) => boolean;
    readonly directories: ReadonlySet<string>;
    readonly children: ReadonlyMap<string, ReadonlyArray<{ name: string; directory: boolean; file: boolean }>>;
}

// Validation is pure. Tests supply an in-memory tree; only the live loader
// below reaches the filesystem, through the Effect FileSystem service.
export function auditDocumentation(root: string, tree: DocumentationFiles, expectedDocs = 0, expectedNav = 0) {
    const readFileSync = (path: string, _encoding: string) => tree.read(path);
    const existsSync = tree.exists;
    const statSync = (path: string) => ({ isDirectory: () => tree.directories.has(path) });
    const readdirSync = (path: string, _options: unknown) => (tree.children.get(path) ?? []).map((entry) => ({
        name: entry.name, isDirectory: () => entry.directory, isFile: () => entry.file,
    }));
const normalizePath = (value: string) => value.split(sep).join('/');
const walk = (directory: string): string[] => {
	const files: string[] = [];
	for (const entry of readdirSync(directory, { withFileTypes: true })) {
		const path = join(directory, entry.name);
		if (entry.isDirectory()) {
			files.push(...walk(path));
		} else if (entry.isFile()) {
			files.push(path);
		}
	}
	return files;
};

const files = walk(root);
const markdownFiles = files.filter((file) => extname(file) === '.md').sort();
const mdxFiles = files.filter((file) => extname(file) === '.mdx').sort();
const metaFiles = files.filter((file) => basename(file) === 'meta.json').sort();
const problems: string[] = [];

if (mdxFiles.length > 0) {
	for (const file of mdxFiles) {
		problems.push(`${normalizePath(relative(root, file))}: canonical content must be .md, not .mdx`);
	}
}

if (expectedDocs > 0 && markdownFiles.length !== expectedDocs) {
	problems.push(`expected ${expectedDocs} Markdown documents, found ${markdownFiles.length}`);
}
if (expectedNav > 0 && metaFiles.length !== expectedNav) {
	problems.push(`expected ${expectedNav} navigation files, found ${metaFiles.length}`);
}

const unquote = (value: string): string => {
	const trimmed = value.trim();
	if (
		(trimmed.startsWith('"') && trimmed.endsWith('"')) ||
		(trimmed.startsWith("'") && trimmed.endsWith("'"))
	) {
		try {
			return trimmed.startsWith('"') ? JSON.parse(trimmed) : trimmed.slice(1, -1).replaceAll("''", "'");
		} catch {
			return trimmed.slice(1, -1);
		}
	}
	return trimmed;
};

const parseDocument = (file: string) => {
	const source = readFileSync(file, 'utf8').replaceAll('\r\n', '\n');
	const match = source.match(/^---\n([\s\S]*?)\n---\n?/);
	if (!match) {
		problems.push(`${normalizePath(relative(root, file))}: missing frontmatter`);
		return { source, body: source, data: {} };
	}

	const data: Record<string, string> = {};
	for (const line of match[1].split('\n')) {
		if (line.trim() === '' || line.trimStart().startsWith('#')) {
			continue;
		}
		const field = line.match(/^([A-Za-z_][A-Za-z0-9_-]*):\s*(.*)$/);
		if (!field) {
			problems.push(`${normalizePath(relative(root, file))}: malformed frontmatter line ${JSON.stringify(line)}`);
			continue;
		}
		if (field[1] in data) {
			problems.push(`${normalizePath(relative(root, file))}: duplicate frontmatter field ${field[1]}`);
			continue;
		}
		data[field[1]] = unquote(field[2]);
	}

	return { source, body: source.slice(match[0].length), data };
};

const stripInlineCode = (line: string) => line.replace(/`[^`]*`/g, '');
const inspectPortableBody = (file: string, body: string) => {
	let fence = '';
	const lines = body.split('\n');
	for (let index = 0; index < lines.length; index += 1) {
		const line = lines[index];
		const fenceMatch = line.match(/^\s*(`{3,}|~{3,})/);
		if (fenceMatch) {
			const marker = fenceMatch[1][0];
			if (fence === '') {
				fence = marker;
			} else if (fence === marker) {
				fence = '';
			}
			continue;
		}
		if (fence !== '') {
			continue;
		}

		const visible = stripInlineCode(line);
		if (/^\s*(import|export)\s/.test(visible)) {
			problems.push(`${normalizePath(relative(root, file))}:${index + 1}: module syntax outside a code fence`);
		}
		if (/<\/?[A-Z][A-Za-z0-9]*(?:\s|>|\/)/.test(visible)) {
			problems.push(`${normalizePath(relative(root, file))}:${index + 1}: executable JSX/MDX component outside a code fence`);
		}
	}
	if (fence !== '') {
		problems.push(`${normalizePath(relative(root, file))}: unclosed code fence`);
	}
};

const requiredFields = [
	'title',
	'description',
	'path',
	'order',
	'meta_title',
	'meta_description',
];
const routeOwners = new Map<string, string>();
const orderOwners = new Map<string, string>();
const documentByRelativePath = new Map<string, string>();

for (const file of markdownFiles) {
	const parsed = parseDocument(file);
	const fileRelative = normalizePath(relative(root, file));
	documentByRelativePath.set(fileRelative.replace(/\.md$/, ''), file);
	for (const field of requiredFields) {
		if (!(field in parsed.data) || String(parsed.data[field]).trim() === '') {
			problems.push(`${fileRelative}: missing frontmatter field ${field}`);
		}
	}

	const route = String(parsed.data.path ?? '').trim();
	if (routeOwners.has(route)) {
		problems.push(`${fileRelative}: duplicate path ${JSON.stringify(route)} also used by ${routeOwners.get(route)}`);
	} else if (route !== '') {
		routeOwners.set(route, fileRelative);
	}

	const orderValue = String(parsed.data.order ?? '').trim();
	const order = Number.parseInt(orderValue, 10);
	if (!/^(0|[1-9][0-9]*)$/.test(orderValue)) {
		problems.push(`${fileRelative}: order must be a non-negative integer`);
	} else {
		const section = String(parsed.data.section ?? '').trim();
		const orderKey = `${section}\0${order}`;
		if (orderOwners.has(orderKey)) {
			problems.push(`${fileRelative}: duplicate order ${order} in section ${JSON.stringify(section)} also used by ${orderOwners.get(orderKey)}`);
		} else {
			orderOwners.set(orderKey, fileRelative);
		}
	}

	inspectPortableBody(file, parsed.body);
}

const referencedDocuments = new Set<string>();
const visitedMeta = new Set<string>();
const separator = /^---.+---$/;

const resolveDocumentEntry = (directory: string, entry: string, metaFile: string): void => {
	const markdown = join(directory, `${entry}.md`);
	const indexMarkdown = join(directory, entry, 'index.md');
	const childDirectory = join(directory, entry);
	if (existsSync(markdown)) {
		referencedDocuments.add(normalizePath(relative(root, markdown)));
		return;
	}
	let resolved = false;
	if (existsSync(indexMarkdown)) {
		referencedDocuments.add(normalizePath(relative(root, indexMarkdown)));
		resolved = true;
	}
	if (existsSync(childDirectory) && statSync(childDirectory).isDirectory()) {
		const childMeta = join(childDirectory, 'meta.json');
		if (existsSync(childMeta)) {
			validateMeta(childMeta);
			return;
		}
	}
	if (resolved) {
		return;
	}
	problems.push(`${normalizePath(relative(root, metaFile))}: unresolved navigation entry ${JSON.stringify(entry)}`);
};

const validateMeta = (metaFile: string): void => {
	const normalizedMeta = normalizePath(relative(root, metaFile));
	if (visitedMeta.has(normalizedMeta)) {
		return;
	}
	visitedMeta.add(normalizedMeta);

	let data: Record<string, unknown>;
	try {
		data = JSON.parse(readFileSync(metaFile, 'utf8'));
	} catch (error) {
		problems.push(`${normalizedMeta}: invalid JSON (${error instanceof Error ? error.message : String(error)})`);
		return;
	}

	const directory = dirname(metaFile);
	for (const field of ['pages', 'folders']) {
		const entries = data[field] ?? [];
		if (!Array.isArray(entries)) {
			problems.push(`${normalizedMeta}: ${field} must be an array`);
			continue;
		}
		for (const entry of entries) {
			if (typeof entry !== 'string' || entry.trim() === '') {
				problems.push(`${normalizedMeta}: ${field} entries must be non-empty strings`);
				continue;
			}
			if (separator.test(entry)) {
				continue;
			}
			resolveDocumentEntry(directory, entry, metaFile);
		}
	}
};

const rootMeta = join(root, 'meta.json');
if (!existsSync(rootMeta)) {
	problems.push('missing root meta.json');
} else {
	validateMeta(rootMeta);
}

for (const file of metaFiles) {
	const normalizedMeta = normalizePath(relative(root, file));
	if (!visitedMeta.has(normalizedMeta)) {
		problems.push(`${normalizedMeta}: navigation file is unreachable from root meta.json`);
	}
}
for (const file of markdownFiles) {
	const fileRelative = normalizePath(relative(root, file));
	if (!referencedDocuments.has(fileRelative)) {
		problems.push(`${fileRelative}: document is unreachable from navigation`);
	}
}

const resolveRelativeLink = (sourceFile: string, target: string) => {
	const clean = target.split('#')[0].split('?')[0];
	if (
		clean === '' ||
		clean.startsWith('/') ||
		clean.startsWith('#') ||
		/^[a-z][a-z0-9+.-]*:/i.test(clean)
	) {
		return true;
	}
	let decoded: string;
	try { decoded = decodeURIComponent(clean); } catch { return false; }
	const candidate = normalize(resolve(dirname(sourceFile), decoded));
	const options = extname(candidate)
		? [candidate]
		: [candidate, `${candidate}.md`, join(candidate, 'index.md')];
	return options.some((path) => existsSync(path));
};

for (const file of markdownFiles) {
	const { body } = parseDocument(file);
	for (const match of body.matchAll(/!?\[[^\]]*\]\(([^)\s]+)(?:\s+["'][^"']*["'])?\)/g)) {
		if (!resolveRelativeLink(file, match[1])) {
			problems.push(`${normalizePath(relative(root, file))}: broken relative link ${match[1]}`);
		}
	}
}

    return { root: normalizePath(root), documents: markdownFiles.length,
        uniqueRoutes: routeOwners.size, navigationFiles: metaFiles.length, problems };
}

export const loadDocumentation = (root: string) => Effect.gen(function* () {
    const fs = yield* FileSystem.FileSystem;
    const contents = new Map<string, string>();
    const present = new Set<string>();
    const directories = new Set<string>();
    const children = new Map<string, Array<{ name: string; directory: boolean; file: boolean }>>();
    const walk = (directory: string): Effect.Effect<void, unknown> => Effect.gen(function* () {
        directories.add(directory);
        present.add(directory);
        const entries: Array<{ name: string; directory: boolean; file: boolean }> = [];
        for (const name of yield* fs.readDirectory(directory)) {
            const path = join(directory, name);
            const info = yield* fs.stat(path);
            present.add(path);
            entries.push({ name, directory: info.type === "Directory", file: info.type === "File" });
            if (info.type === "Directory") yield* walk(path);
            else if (info.type === "File" && (extname(path) === ".md" || basename(path) === "meta.json")) {
                contents.set(path, yield* fs.readFileString(path, "utf-8"));
            }
        }
        children.set(directory, entries);
    });
    yield* walk(root);
    // Relative Markdown links may point outside docs, so include those targets
    // without recursively reading unrelated repository content.
    for (const [source, text] of contents) {
        if (extname(source) !== ".md") continue;
        for (const match of text.matchAll(/!?\[[^\]]*\]\(([^)\s]+)(?:\s+["'][^"']*["'])?\)/g)) {
            const clean = match[1].split("#")[0].split("?")[0];
            if (!clean || clean.startsWith("/") || /^[a-z][a-z0-9+.-]*:/i.test(clean)) continue;
            let decoded: string;
            try { decoded = decodeURIComponent(clean); } catch { continue; }
            const candidate = normalize(resolve(dirname(source), decoded));
            for (const path of extname(candidate) ? [candidate] : [candidate, `${candidate}.md`, join(candidate, "index.md")]) {
                if (yield* fs.exists(path)) present.add(path);
            }
        }
    }
    return { read: (path: string) => contents.get(path) ?? "", exists: (path: string) => present.has(path), directories, children } satisfies DocumentationFiles;
});

export const run = (root: string, expectedDocs = 0, expectedNav = 0) => Effect.gen(function* () {
    const tree = yield* loadDocumentation(root);
    const result = auditDocumentation(root, tree, expectedDocs, expectedNav);
    if (result.problems.length) return yield* fail(`Documentation content check failed with ${result.problems.length} problem(s):\n${result.problems.map((problem) => `- ${problem}`).join("\n")}`);
    yield* Console.log(JSON.stringify({ ...result, problems: 0 }));
});

export const command = script("check-docs-content", {
    description: "Validate portable Markdown, frontmatter, navigation and relative links.",
    flags: {
        root: Flag.String("root").pipe(Flag.withDefault("docs")),
        expectedDocs: Flag.Int("expected-docs").pipe(Flag.withDefault(0)),
        expectedNav: Flag.Int("expected-nav").pipe(Flag.withDefault(0)),
    },
    run: (input) => run(resolve(input.root), input.expectedDocs, input.expectedNav),
});
if (import.meta.main) main(command);
