# Ghép video: lồng tiếng từng cảnh (edge-tts, giọng Hoài My) + khung hình → clip → nối thành MP4 có chương.
import asyncio
import hashlib
import json
import os
import subprocess
import sys

import edge_tts

HERE = os.path.dirname(os.path.abspath(__file__))
VOICE = 'vi-VN-HoaiMyNeural'
FPS = 25
LEAD = 0.35    # lặng trước khi đọc
TAIL = 0.65    # lặng sau khi đọc
AUD = os.path.join(HERE, 'audio'); CLIPS = os.path.join(HERE, 'clips')
os.makedirs(AUD, exist_ok=True); os.makedirs(CLIPS, exist_ok=True)
scenes = json.load(open(os.path.join(HERE, 'scenes.json'), encoding='utf-8'))['scenes']
only = set(sys.argv[1].split(',')) if len(sys.argv) > 1 and sys.argv[1] else None


def dur(p):
    return float(subprocess.check_output(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', p]).decode().strip())


async def tts(s):
    h = hashlib.md5((VOICE + s['say']).encode('utf-8')).hexdigest()[:10]
    out = os.path.join(AUD, s['id'] + '-' + h + '.mp3')
    if os.path.exists(out) and os.path.getsize(out) > 2000:
        return out
    for _ in range(5):
        try:
            await edge_tts.Communicate(s['say'], VOICE).save(out)
            if os.path.getsize(out) > 2000:
                return out
        except Exception as e:
            print('  thử lại', s['id'], type(e).__name__)
            await asyncio.sleep(1.5)
    raise RuntimeError('Không tạo được giọng cho ' + s['id'])


async def all_tts():
    sem = asyncio.Semaphore(3)

    async def one(s):
        async with sem:
            return s['id'], await tts(s)
    return dict(await asyncio.gather(*[one(s) for s in scenes]))


audio = asyncio.run(all_tts())
print('Đã có', len(audio), 'đoạn lồng tiếng')

clips = []
t0 = 0.0
chapters = []
for s in scenes:
    a = audio[s['id']]
    d = dur(a) + LEAD + TAIL
    clip = os.path.join(CLIPS, s['id'] + '.mp4')
    img = os.path.join(HERE, 'frames', s['id'] + '.png')
    frames = int(round(d * FPS))
    vf = ("scale=1920:1080,zoompan=z='min(1.0+0.00022*on,1.03)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d=1:s=1920x1080:fps=%d,"
          "fade=t=in:st=0:d=0.25,fade=t=out:st=%.2f:d=0.25,format=yuv420p") % (FPS, d - 0.25)
    cmd = ['ffmpeg', '-y', '-v', 'error', '-loop', '1', '-framerate', str(FPS), '-i', img, '-i', a,
           '-filter_complex', '[0:v]%s[v];[1:a]adelay=%d|%d,apad,atrim=0:%.3f[a]' % (vf, int(LEAD * 1000), int(LEAD * 1000), d),
           '-map', '[v]', '-map', '[a]', '-t', '%.3f' % d, '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '21', '-r', str(FPS),
           '-c:a', 'aac', '-b:a', '160k', '-ar', '44100', '-ac', '2', clip]
    if not (only and s['id'] not in only) or not os.path.exists(clip):
        subprocess.check_call(cmd)
    clips.append(clip)
    if s.get('tag') and (not chapters or chapters[-1][1] != s['tag']):
        chapters.append((t0, s['tag']))
    elif s['id'] == '00-intro':
        chapters.append((t0, 'Mở đầu'))
    elif s['id'] == '99-outro':
        chapters.append((t0, 'Tóm tắt'))
    t0 += d
    print('  %-14s %5.1fs' % (s['id'], d))

lst = os.path.join(HERE, 'clips.txt')
open(lst, 'w', encoding='utf-8').write(''.join("file '%s'\n" % c.replace('\\', '/') for c in clips))
meta = os.path.join(HERE, 'chapters.ffmeta')
with open(meta, 'w', encoding='utf-8') as f:
    f.write(';FFMETADATA1\ntitle=Hướng dẫn sử dụng trang quản trị vfwild.vn\n')
    for i, (st, name) in enumerate(chapters):
        en = chapters[i + 1][0] if i + 1 < len(chapters) else t0
        f.write('[CHAPTER]\nTIMEBASE=1/1000\nSTART=%d\nEND=%d\ntitle=%s\n' % (st * 1000, en * 1000 - 1, name))
final = os.path.join(HERE, 'Huong-dan-quan-tri-vfwild.mp4')
subprocess.check_call(['ffmpeg', '-y', '-v', 'error', '-f', 'concat', '-safe', '0', '-i', lst, '-i', meta, '-map_metadata', '1', '-c', 'copy', '-movflags', '+faststart', final])
open(os.path.join(HERE, 'chapters.txt'), 'w', encoding='utf-8').write('\n'.join('%d:%02d  %s' % (st // 60, st % 60, n) for st, n in chapters) + '\n')
print('Tổng %.0f giây (%d:%02d) → %s (%.1f MB)' % (t0, t0 // 60, t0 % 60, final, os.path.getsize(final) / 1e6))
