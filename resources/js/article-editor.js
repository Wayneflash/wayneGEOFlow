import { marked } from 'marked';

marked.setOptions({
    gfm: true,
    breaks: true,
});

window.marked = marked;
