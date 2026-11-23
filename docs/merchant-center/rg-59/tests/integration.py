import urllib.request,urllib.parse,http.cookiejar,re,json,sys
host=sys.argv[1] if len(sys.argv)>1 else 'https://testarea.roungroun.com'
jar=http.cookiejar.CookieJar(); redirects=[]
class Audit(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,req,fp,code,msg,h,newurl):
  redirects.append({'code':code,'target':newurl,'cache':h.get('Cache-Control'),'robots':h.get('X-Robots-Tag')})
  return super().redirect_request(req,fp,code,msg,h,newurl)
opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar),Audit())
def get(path):
 with opener.open(host+path,timeout=60) as r:return r.geturl(),r.read().decode()
def cart_items(html):
 return re.findall(r'<input[^>]+name="cart\[([^\]]+)\]\[qty\]"[^>]*>',html)
results=[]
for label,path in [('variation','/?rg_checkout_offer=gla_1101&quantity=1&rg_destination=cart'),('repeat','/?rg_checkout_offer=gla_1101&quantity=1&rg_destination=cart'),('simple','/?rg_checkout_offer=gla_938&quantity=1&rg_destination=cart'),('quantity-zero','/?rg_checkout_offer=gla_938&quantity=0'),('malformed','/?rg_checkout_offer=gla_938evil'),('missing-attributes','/?rg_checkout_offer=gla_1099'),('out-of-stock','/?rg_checkout_offer=gla_1199'),('checkout','/?rg_checkout_offer=gla_1101&quantity=1&utm_source=rg59-test')]:
 redirects.clear();url,body=get(path)
 title=re.search(r'<title>(.*?)</title>',body,re.S)
 quantities=re.findall(r'<input[^>]*(?:name="cart\[[^"]+\]\[qty\]"|class="[^"]*qty[^"]*")[^>]*>',body)
 notices=re.findall(r'<(?:li|div)[^>]*(?:woocommerce-error|woocommerce-notice|role="alert")[^>]*>(.*?)</(?:li|div)>',body,re.S)
 results.append({'test':label,'final':url,'title':title.group(1) if title else None,'redirects':list(redirects),'quantities':quantities,'notices':[re.sub('<[^>]+>',' ',x).strip()[:300] for x in notices]})
 open('/tmp/rg59-'+label+'.html','w').write(body)
# Remove only lines in this anonymous test cart, using its own native nonce URLs.
url,body=get('/cart/')
remove=list(dict.fromkeys(re.findall(r'href="([^"]*remove_item=[^"]+)"',body)))
import html
for link in remove:
 link=html.unescape(link)
 if link.startswith(host):get(link[len(host):])
_,empty=get('/cart/')
print(json.dumps({'tests':results,'cleanup_links':len(remove),'empty':bool(re.search('cart is (?:currently )?empty',empty,re.I))},indent=2))
