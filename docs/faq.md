---
title: "FAQ"
nav_order: 10
description: "Short answers about darvis/api-x: what it is, what X charges, which keys it needs, links, the daily limit and the MCP server for AI agents."
faq: true
---

# Frequently asked questions

{% for item in site.data.faq %}
## {{ item.q }}

{{ item.a | markdownify }}
{% endfor %}
