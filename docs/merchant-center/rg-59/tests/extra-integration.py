import urllib.request,http.cookiejar,re,html,json
op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
base='https://testarea.roungroun.com'; out=[]
for name,path in [('variable-parent-with-attributes','/?rg_checkout_offer=1099&attribute_colour=Beige%2FBlack&quantity=1&rg_destination=cart'),('single-writer-native-query-conflict','/?rg_checkout_offer=gla_1101&quantity=1&rg_destination=cart&add-to-cart=938')]:
 with op.open(base+path,timeout=60) as r:s=r.read().decode();url=r.geturl()
 quantities=re.findall(r'name="cart\[[^\"]+\]\[qty\]"\s+value="([^\"]+)"',s)
 assert quantities==['1'] and 'Beige/Black' in s,(name,quantities)
 out.append({'test':name,'final':url,'quantity':1,'line_count':1,'exact_variant':'Beige/Black','pass':True})
links=list(dict.fromkeys(html.unescape(x) for x in re.findall(r'href="([^\"]*remove_item=[^\"]+)"',s)))
for link in links:
 with op.open(link,timeout=60) as r:r.read()
print(json.dumps({'tests':out,'test_lines_removed':len(links)},indent=2))
