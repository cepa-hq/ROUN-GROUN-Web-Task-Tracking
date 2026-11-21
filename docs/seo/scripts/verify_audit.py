"""RG-53 read-only assertions against audit_public.py JSON; no website writes."""
import json, html, sys
from pathlib import Path
root=Path(__file__).resolve().parent
p=json.loads((root/(sys.argv[1] if len(sys.argv)>1 else 'audit-after.json')).read_text())
by={x['requested']:x for x in p['pages']}; eligible=set(p['sitemap_urls']); checks=[]
def ok(label,condition):
    checks.append({'check':label,'passed':bool(condition)})
    if not condition:raise AssertionError(label)
ok('17 unique eligible URLs / 18 XML entries (Shop in two sitemaps)',len(eligible)==17 and len(p['sitemap_urls'])==18)
for url in eligible:
    x=by[url]
    ok(url+' indexable page integrity',x.get('status')==200 and len(x.get('titles',[]))==1 and len(x.get('description',[]))==1 and bool(x['description'][0]) and x.get('canonical')==[url] and 'noindex' not in ' '.join(x.get('robots',[])))
for x in by.values():
    if 'html' in x.get('content_type',''):
        ok(x['requested']+' HTML head/schema integrity',len(x.get('titles',[]))==1 and len(x.get('canonical',[]))<=1 and all(s['valid'] for s in x.get('schemas',[])) and not x.get('staging_leak') and not x.get('legacy_apparel'))
for suffix in ['/cart/','/checkout/','/my-account/','/wishlist/','/cancellation-request/','/return-request/','/?s=bands','/product-category/uncategorized/','/brand/roun-groun/','/product-category/sportswear-sportsgear-activewear/']:
    u='https://roungroun.com'+suffix
    ok(u+' excluded', 'noindex' in ' '.join(by[u].get('robots',[])) and u not in eligible)
products=[]
for x in by.values():
    if '/product/' not in x['requested']:continue
    nodes=[z for s in x.get('schemas',[]) for z in s.get('data',{}).get('@graph',[]) if z.get('@type')=='Product']
    ok(x['requested']+' single Product',len(nodes)==1)
    q=nodes[0];o=q['offers'];products.append(q)
    ok(x['requested']+' factual offer/brand',q.get('brand',{}).get('name')=='Roun Groun' and len(html.unescape(q['name']))<=150 and o.get('priceCurrency')=='INR' and float(o.get('price',0))>0 and o.get('url')==x['url'] and 'aggregateRating' not in q)
ok('six products',len(products)==6)
ok('whistle accurately out of stock',next(x for x in products if 'Whistle' in x['name'])['offers']['availability'].endswith('/OutOfStock'))
ok('404 retained',by['https://roungroun.com/rg-seo-audit-nonexistent-53/']['status']==404)
for old,new in [('http://roungroun.com/','https://roungroun.com/'),('https://www.roungroun.com/','https://roungroun.com/'),('https://roungroun.com/about/','https://roungroun.com/about-roun-groun/'),('https://roungroun.com/refund_returns/','https://roungroun.com/refund-returns/'),('https://roungroun.com/wp-sitemap.xml','https://roungroun.com/sitemap_index.xml')]:
    ok(old+' redirect retained',by[old].get('url')==new and by[old].get('status')==200)
ok('sorting canonical retained',by['https://roungroun.com/shop/?orderby=price']['canonical']==['https://roungroun.com/shop/'])
for u,x in by.items():
    if u.endswith('.webp'):ok(u+' icon reachable',x.get('status')==200)
report={'captured_utc':p['utc'],'checks':len(checks),'all_passed':True,'note':'Local assertions are not Google certification. Price/stock current at audit time; no authenticated customer pages or transactions requested.','results':checks}
(root/'verification-results.json').write_text(json.dumps(report,indent=2))
print(str(len(checks))+' assertions passed')
