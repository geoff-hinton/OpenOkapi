#!/usr/bin/env python3
import matplotlib
matplotlib.use('Agg')

import matplotlib.pyplot as plt
import numpy as np
import matplotlib.font_manager as fm
import argparse

# ========== 命令行参数解析 ==========
parser = argparse.ArgumentParser(description='基于 I_grid 斜率的储能动态跟随/保持控制')
parser.add_argument('--k', type=float, default=0.75, help='比例系数 k (默认 0.75)')
parser.add_argument('--val', type=float, default=600.0, help='储能最大补偿电流 I_max (A)，默认 600')
parser.add_argument('--di_dt_max', type=float, default=200000.0,
                    help='储能电流最大变化率 (A/s)，默认 200000')
parser.add_argument('--dref', type=float, default=44400.0,
                    help='I_grid 斜率触发阈值 (A/s)，默认 44400')
parser.add_argument('--output_table', type=str, default=None,
                    help='输出表格文件路径（可选）')
args = parser.parse_args()

k = args.k
I_max = args.val
di_dt_max = args.di_dt_max
Dref = args.dref

# ========== 字体设置 ==========
try:
    font_path = '/usr/share/fonts/truetype/wqy/wqy-microhei.ttc'
    prop = fm.FontProperties(fname=font_path)
except:
    prop = None
    print("警告：未找到中文字体，将使用默认字体")

# -------------------- 固定数据（时间轴和负载电流）--------------------
t = [
    10.0000, 10.0476, 10.0952, 10.1429, 10.1905, 10.2381, 10.2857, 10.3333, 10.3810, 10.4286,
    10.4762, 10.5238, 10.5714, 10.6190, 10.6667, 10.7143, 10.7619, 10.8095, 10.8571, 10.9048,
    10.9524, 11.0000, 11.0476, 11.0952, 11.1429, 11.1905, 11.2381, 11.2857, 11.3333, 11.3810,
    11.4286, 11.4762, 11.5238, 11.5714, 11.6190, 11.6667, 11.7143, 11.7619, 11.8095, 11.8571,
    11.9048, 11.9524, 12.0000, 12.0476, 12.0952, 12.1429, 12.1905, 12.2381, 12.2857, 12.3333,
    12.3810, 12.4286, 12.4762, 12.5238, 12.5714, 12.6190, 12.6667, 12.7143, 12.7619, 12.8095,
    12.8571, 12.9048, 12.9524, 13.0000, 13.0476, 13.0952, 13.1429, 13.1905, 13.2381, 13.2857,
    13.3333, 13.3810, 13.4286, 13.4762, 13.5238, 13.5714, 13.6190, 13.6667, 13.7143, 13.7619,
    13.8095, 13.8571, 13.9048, 13.9524, 14.0000, 14.0476, 14.0952, 14.1429, 14.1905, 14.2381,
    14.2857, 14.3333, 14.3810, 14.4286, 14.4762, 14.5238, 14.5714, 14.6190, 14.6667, 14.7143,
    14.7619, 14.8095, 14.8571, 14.9048, 14.9524, 15.0000
]

I_load = [
    5.00, 14.52, 24.05, 33.57, 43.10, 52.62, 62.14, 71.67, 81.19, 90.71,
    100.24, 109.76, 119.29, 128.81, 138.33, 147.86, 157.38, 166.90, 176.43, 185.95,
    195.48, 205.00, 209.76, 214.52, 219.29, 224.05, 228.81, 233.57, 238.33, 243.10,
    247.86, 252.62, 257.38, 262.14, 266.90, 271.67, 276.43, 281.19, 285.95, 290.71,
    295.48, 300.24, 305.00, 309.76, 314.52, 319.29, 324.05, 328.81, 333.57, 338.33,
    343.10, 347.86, 352.62, 357.38, 362.14, 366.90, 371.67, 376.43, 381.19, 385.95,
    390.71, 395.48, 400.24, 405.00, 409.76, 414.52, 419.29, 424.05, 428.81, 433.57,
    438.33, 443.10, 447.86, 452.62, 457.38, 462.14, 466.90, 471.67, 476.43, 481.19,
    485.95, 490.71, 495.48, 500.24, 505.00, 509.76, 514.52, 519.29, 524.05, 528.81,
    533.57, 538.33, 543.10, 547.86, 552.62, 557.38, 562.14, 566.90, 571.67, 576.43,
    581.19, 585.95, 590.71, 595.48, 600.24, 605.00
]

# ========== 物理参数 ==========
I_normal = 5.0
dt = (t[1] - t[0]) * 1e-3          # s
delta_I_max = di_dt_max * dt       # 每步最大电流变化量 (A)

n = len(t)

# 预先分配数组
I_bat_target = [0.0] * n
I_bat_actual = [0.0] * n
I_grid = [0.0] * n
dI_grid = [0.0] * n
mode = ['保持'] * n   # '跟随' 或 '保持'

# 初始状态：储能电流为0，I_grid = I_load
bat_current = 0.0
following = False      # 初始是否处于跟随模式

# 第0个点（t=10.0000）
I_bat_actual[0] = 0.0
I_grid[0] = I_load[0]
# 第一个点的斜率暂时无法计算，后续会从第二个点开始更新

# 主循环，从第二个点开始，因为需要前一个 I_grid 计算斜率
for i in range(1, n):
    # 当前步的 I_grid 依赖于当前步的 I_bat_actual，而 I_bat_actual 又依赖于前一步状态和当前斜率标志
    # 为了计算斜率，需要用到前一步的 I_grid，所以先根据前一步的状态和当前负载决定是否跟随，
    # 然后计算当前 I_bat_actual，再计算当前 I_grid，最后再为下一步计算斜率。
    # 但当前步的斜率判断应该基于上一步的 I_grid 吗？实际上物理上是实时的，但数值上我们可以认为：
    # 在时间步 i 开始时，我们已知上一步的 I_grid[i-1] 和上一步的储能状态，然后决定这一步是否跟随。
    # 先根据上一步的 I_grid 斜率判断当前是否跟随（滞后一个步长，但可以接受）。
    if i >= 2:
        # 使用上一个时间步的 I_grid 斜率（即 dI_grid[i-1]）来决定当前步的模式
        if dI_grid[i-1] >= Dref:
            if not following:
                following = True
        else:
            if following:
                following = False
    
    # 计算目标电流
    if following:
        target = k * (I_load[i] - I_normal)
        if target > I_max:
            target = I_max
        elif target < 0:
            target = 0.0
        I_bat_target[i] = target
        mode[i] = '跟随'
    else:
        # 保持模式：目标为当前实际电流（即保持上次的值）
        target = bat_current
        I_bat_target[i] = target
        mode[i] = '保持'
    
    # 实际电流向目标逼近，受斜率限制
    diff = target - bat_current
    if diff > delta_I_max:
        bat_current += delta_I_max
    elif diff < -delta_I_max:
        bat_current -= delta_I_max
    else:
        bat_current = target
    if bat_current > I_max:
        bat_current = I_max
    if bat_current < 0:
        bat_current = 0.0
    I_bat_actual[i] = bat_current
    
    # 计算当前 I_grid
    I_grid[i] = I_load[i] - I_bat_actual[i]
    
    # 计算当前 I_grid 的斜率（用于下一步判断）
    if i >= 1:
        dI_grid[i] = (I_grid[i] - I_grid[i-1]) / dt
    else:
        dI_grid[i] = 0.0

# 第一个点的斜率与第二个点相同（避免NaN）
dI_grid[0] = dI_grid[1]

# 由于第一个点之前没有斜率判断，初始 following 为 False，合理
# 但为了图表美观，可以修正 mode 数组
mode[0] = '保持'

# -------------------- 输出数据表格 --------------------
def print_table():
    header = f"{'t(ms)':>8}  {'I_load(A)':>10}  {'I_bat_target(A)':>15}  {'I_bat_actual(A)':>15}  {'I_grid(A)':>10}  {'dI_grid(A/s)':>15}  {'Mode':>6}"
    sep = '-' * 95
    lines = [header, sep]
    for i in range(n):
        line = f"{t[i]:8.4f}  {I_load[i]:10.2f}  {I_bat_target[i]:15.2f}  {I_bat_actual[i]:15.2f}  {I_grid[i]:10.2f}  {dI_grid[i]:15.0f}  {mode[i]:>6}"
        lines.append(line)
    return '\n'.join(lines)

table_str = print_table()
print("\n===== 基于 I_grid 斜率的动态控制（Dref = {} A/s）=====".format(Dref))
print(table_str)

if args.output_table:
    with open(args.output_table, 'w', encoding='utf-8') as f:
        f.write(table_str)
    print(f"\n表格已保存至: {args.output_table}")

# -------------------- 绘图 --------------------
plt.figure(figsize=(12, 8))

# 主坐标轴：电流
plt.plot(t, I_load, 'b--', linewidth=1.5, label='负载电流 I_load (冲击电流)', alpha=0.8)
plt.plot(t, I_bat_actual, 'g-.', linewidth=1.5, label=f'储能补偿电流 I_bat (k={k}, Imax={I_max}A, di/dt_max={di_dt_max}A/s)', alpha=0.8)
plt.plot(t, I_grid, 'r-', linewidth=2, label='电网侧电流 I_grid (补偿后)', alpha=0.9)

# 副坐标轴：I_grid 斜率
ax2 = plt.twinx()
ax2.plot(t, dI_grid, 'm:', linewidth=1, label='I_grid 斜率 (A/s)', alpha=0.6)
ax2.axhline(y=Dref, color='orange', linestyle='--', linewidth=1, label=f'触发阈值 Dref = {Dref} A/s')
if prop:
    ax2.set_ylabel('电流变化率 (A/s)', fontsize=12, fontproperties=prop)
else:
    ax2.set_ylabel('dI_grid/dt (A/s)', fontsize=12)

# 标注进入跟随模式的时刻
follow_start_times = []
for i in range(1, n):
    if mode[i] == '跟随' and mode[i-1] == '保持':
        follow_start_times.append(t[i])
for tt in follow_start_times:
    plt.axvline(x=tt, color='black', linestyle=':', linewidth=1, alpha=0.7)
    if prop:
        plt.text(tt, max(I_load)*0.9, '开始跟随', rotation=90, fontsize=8, va='top', fontproperties=prop)
    else:
        plt.text(tt, max(I_load)*0.9, 'Follow', rotation=90, fontsize=8, va='top')

# 阴影区域
plt.fill_between(t, I_load, I_grid, where=(np.array(I_load) > np.array(I_grid)),
                 color='yellow', alpha=0.3, label='储能补偿能量区域')

if prop:
    plt.xlabel('时间 t (ms)', fontsize=12, fontproperties=prop)
    plt.ylabel('电流 I (A)', fontsize=12, fontproperties=prop)
    plt.title(f'基于 I_grid 斜率的储能动态控制 (Dref={Dref} A/s, k={k}, Imax={I_max}A)', fontsize=14, fontproperties=prop)
    plt.legend(loc='upper left', prop=prop)
    ax2.legend(loc='upper right', prop=prop)
else:
    plt.xlabel('Time (ms)', fontsize=12)
    plt.ylabel('Current (A)', fontsize=12)
    plt.title(f'Dynamic Control based on I_grid slope (Dref={Dref} A/s, k={k}, Imax={I_max}A)', fontsize=14)
    plt.legend(loc='upper left')
    ax2.legend(loc='upper right')

plt.grid(True, linestyle='--', alpha=0.5)
plt.xlim(10, 15.5)
plt.ylim(0, max(I_load)*1.05)
plt.tight_layout()

output_file = f'Figure4_k{k}_Imax{I_max}_didt{di_dt_max}_Dref{Dref}_dynamic.png'
plt.savefig(output_file, dpi=300)
print(f"图片已保存为: {output_file}")