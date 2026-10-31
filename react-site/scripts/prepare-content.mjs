import { readFileSync, writeFileSync, mkdirSync, cpSync } from "node:fs";
import { resolve, dirname } from "node:path";
const source = resolve(process.cwd(), "..");
const output = resolve(process.cwd(), "public");
mkdirSync(output, {recursive:true});
for(const folder of ["images","sponsoren","bestanden"]) cpSync(resolve(source,folder),resolve(output,folder),{recursive:true});
cpSync(resolve(source,"data/sponsors.json"),resolve(output,"sponsors.json"));
const names=["index","bestuur","agenda","tentoonstelling","sponsoren","ringen","lidworden","contact"];
const content={};
for(const name of names){
 const file=name==="index"?"index.html":`pages/${name}.html`;
 const html=readFileSync(resolve(source,file),"utf8");
 let body=html.split(/<\/nav>/i)[1]?.split(/<!--\s*Sponsoren:\s*gold\s*-->/i)[0]??"";
 body=body.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,"").replace(/\son\w+\s*=\s*"[^"]*"/gi,"").replace(/\saction="[^"]*"/gi,"");
 // Rewrite all historical links to the React routes and preserve public images.
 body=body.replace(/(?:\.\.\/)?images\//g,"/images/").replace(/(?:\.\.\/)?bestanden\//g,"/bestanden/");
 body=body.replace(/(?:\.\.\/)?index\.html/g,"/").replace(/(?:\.\.\/)?pages\/([a-z_]+)\.html/g,"/$1").replace(/href="([a-z_]+)\.html"/g,'href="/$1"');
 // Legacy contact FormMail is not carried over: avoid accidentally submitting to insecure CGI.
 if(name==="contact") body=body.replace(/<form\b[\s\S]*?<\/form>/i,'<p>Neem contact op via <a href="mailto:info@devogelvriendroden.nl">info@devogelvriendroden.nl</a>. Het nieuwe contactformulier volgt na de veilige serverintegratie.</p>');
 content[name]=body;
}
writeFileSync(resolve(output,"content.json"),JSON.stringify(content));
