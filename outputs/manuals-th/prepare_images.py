from pathlib import Path
from PIL import Image, ImageDraw
import json, shutil
p=Path(__file__).parent/'screenshots'
for name in ['admin-detail','admin-registrations']:
    im=Image.open(p/f'{name}-raw.png').convert('RGB'); d=ImageDraw.Draw(im)
    for r in json.loads((p/f'{name}-masks.json').read_text()):
        x,y,w,h=[r[k] for k in ['x','y','width','height']]
        d.rectangle((int(x),int(y),int(x+w+1),int(y+h+1)),fill='#e2e6e3')
    im.save(p/f'{name}.png')
def crop(src,dst,box):
    Image.open(p/src).crop(box).save(p/dst)
crop('frontend-desktop.png','frontend-intro.png',(0,0,1264,900))
crop('frontend-desktop.png','frontend-form.png',(350,900,920,1500))
crop('frontend-desktop.png','frontend-consent.png',(350,1490,920,1820))
crop('sprops-full.png','sprops-session.png',(350,900,920,1180))
crop('admin-registrations.png','admin-filters.png',(250,100,1264,590))
crop('admin-registrations.png','admin-table.png',(250,590,1264,1220))
crop('admin-detail.png','admin-guest.png',(270,210,1230,610))
crop('admin-detail.png','admin-consent.png',(270,610,1230,1130))
shutil.copyfile('C:/Users/USER/AppData/Local/Temp/codex-clipboard-6afd1621-ca32-47c4-ab88-80503247772c.png',p/'thankyou-reference.png')
thumbs=[]
for name in ['frontend-form','frontend-consent','sprops-session','admin-filters','admin-table','admin-guest','admin-consent','admin-overview','admin-sessions','admin-session-edit']:
    im=Image.open(p/f'{name}.png').convert('RGB'); im.thumbnail((600,550)); frame=Image.new('RGB',(620,590),'white');frame.paste(im,(10,30));ImageDraw.Draw(frame).text((10,5),name,fill='black');thumbs.append(frame)
sheet=Image.new('RGB',(1240,590*5),'#ccc')
for i,im in enumerate(thumbs):sheet.paste(im,((i%2)*620,(i//2)*590))
sheet.save(p/'image-review.jpg')
