"""RG-53: bounded anonymous read-only HTTP/HTML SEO audit. No cookies or writes."""
from concurrent.futures import ThreadPoolExecutor
from html.parser import HTMLParser
from pathlib import Path
import json, time, urllib.request, urllib.error, urllib.parse
import xml.etree.ElementTree as ET

ROOT=Path(__file__).resolve().parent
BASE='https://roungroun.com'
NS={'s':'http://www.sitemaps.org/schemas/sitemap/0.9'}

class Page(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.meta=[]; self.links=[]; self.anchors=[]; self.images=[]; self.h1=[]
        self.title=''; self.titles=[]; self.in_head=False; self.in_title=False; self.in_h1=False; self.schema=[]
        self.json_script=False; self.json_text=''; self.lang=None; self.text=[]; self.skip=0
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if tag=='html': self.lang=a.get('lang')
        if tag=='head':self.in_head=True
        if tag=='title' and self.in_head: self.in_title=True; self.titles.append('')
        if tag=='h1': self.in_h1=True; self.h1.append('')
        if tag=='meta': self.meta.append(a)
        if tag=='link': self.links.append(a)
        if tag=='a' and 'href' in a:self.anchors.append(a)
        if tag=='img':self.images.append({k:a.get(k) for k in ['src','alt','width','height','loading']})
        if tag=='script':
            self.json_script=a.get('type')=='application/ld+json';self.json_text=''
        if tag in ('script','style'): self.skip+=1
    def handle_endtag(self,tag):
        if tag=='head':self.in_head=False
        if tag=='title':self.in_title=False
        if tag=='h1':self.in_h1=False
        if tag=='script' and self.json_script:
            try:self.schema.append({'valid':True,'data':json.loads(self.json_text)})
            except (ValueError,TypeError):self.schema.append({'valid':False,'raw':self.json_text[:300]})
            self.json_script=False
        if tag in ('script','style'):self.skip=max(0,self.skip-1)
    def handle_data(self,s):
        if self.in_title:self.title+=s; self.titles[-1]+=s
        if self.in_h1 and self.h1:self.h1[-1]+=s
        if self.json_script:self.json_text+=s
        if not self.skip:self.text.append(s)

def fetch(url):
    start=time.monotonic()
    try:
        try:r=urllib.request.urlopen(url,timeout=30)
        except urllib.error.HTTPError as e:r=e
        with r:
            raw=r.read(); headers=dict(r.headers); status=r.status; final=r.url
        text=raw.decode('utf-8','replace')
        out={'requested':url,'url':final,'status':status,'seconds':round(time.monotonic()-start,3),
             'bytes':len(raw),'content_type':headers.get('Content-Type',headers.get('content-type','')),
             'x_robots_tag':headers.get('X-Robots-Tag',headers.get('x-robots-tag',''))}
        if 'html' in out['content_type']:
            p=Page();p.feed(text)
            out.update(title=p.title.strip(),titles=[x.strip() for x in p.titles],h1=[x.strip() for x in p.h1],lang=p.lang,
              description=[x.get('content','') for x in p.meta if x.get('name','').lower()=='description'],
              robots=[x.get('content','') for x in p.meta if x.get('name','').lower()=='robots'],
              canonical=[x.get('href') for x in p.links if 'canonical' in x.get('rel','').split()],
              og={x.get('property'):x.get('content') for x in p.meta if x.get('property','').startswith('og:')},
              icons=[x.get('href') for x in p.links if 'icon' in x.get('rel','')],
              schemas=p.schema,anchors=p.anchors,images=p.images,
              staging_leak='testarea.roungroun.com' in text,
              legacy_apparel='Dedicated Fitness and Sports Apparel Brand' in text,
              public_text=' '.join(' '.join(p.text).split())[:16000])
        else:out['text']=text
        return out
    except Exception as e:return {'requested':url,'error':type(e).__name__+': '+str(e)}

def audit(label):
    index=fetch(BASE+'/sitemap_index.xml')
    if index.get('status')!=200 or 'xml' not in index.get('content_type',''):raise RuntimeError(index)
    sitemap_urls=[x.text for x in ET.fromstring(index['text']).findall('s:sitemap/s:loc',NS)]
    maps=[fetch(x) for x in sitemap_urls]
    urls=[]
    for sm in maps:
        if sm.get('status')!=200:continue
        urls.extend(x.text for x in ET.fromstring(sm['text']).findall('s:url/s:loc',NS))
    extra=[BASE+x for x in ['/cart/','/checkout/','/my-account/','/wishlist/','/about/',
       '/cancellation-request/','/return-request/','/?s=bands','/shop/?orderby=price',
       '/brand/roun-groun/','/product-category/uncategorized/','/product-category/sportswear-sportsgear-activewear/',
       '/refund_returns/','/wp-sitemap.xml','/wp-sitemap-posts-page-1.xml',
       '/wp-sitemap-posts-product-1.xml','/rg-seo-audit-nonexistent-53/','/robots.txt',
       '/wp-content/uploads/2026/11/cropped-Multicolour-Gradient-RG-Logo.webp',
       '/wp-content/uploads/2026/11/cropped-Multicolour-Gradient-RG-Logo-32x32.webp']]
    all_urls=list(dict.fromkeys(urls+extra+['http://roungroun.com/','https://www.roungroun.com/']))
    with ThreadPoolExecutor(max_workers=2) as pool:
        pages=[]
        for item in pool.map(fetch,all_urls):
            pages.append(item)
            print('FETCH',item['requested'],item.get('status',item.get('error')),flush=True)
    result={'label':label,'utc':time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime()),
      'sitemap_index':index,'sitemaps':maps,'sitemap_urls':urls,'pages':pages}
    (ROOT/('audit-'+label+'.json')).write_text(json.dumps(result,indent=2))
    for p in pages:
        types=[]
        for s in p.get('schemas',[]):
            d=s.get('data',{});types.extend(x.get('@type') for x in (d.get('@graph',[d]) if isinstance(d,dict) else d) if isinstance(x,dict))
        print(json.dumps({k:p.get(k) for k in ['requested','url','status','error','title','h1','description','robots','canonical','staging_leak']},ensure_ascii=False),flush=True)
        if types:print('SCHEMA',json.dumps(types),flush=True)
    print('SITEMAP_URLS',len(urls),flush=True)

if __name__=='__main__':
    import sys
    audit(sys.argv[1] if len(sys.argv)>1 else 'before')
