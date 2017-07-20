#!/bin/bash

start_time=$(date +%s)

rm -rf output
mkdir output

# 打包
cd output
mkdir jet
cp -rf ../lib ../smarty_plugins ../config
tar -cjf jet.tar.bz2 jet
rm -rf atom
cp -rf ../noahdes ./
cd ..

# 耗时
end_time=$(date +%s)
compile_time=$(($end_time - $start_time))
echo "Compiled in ${compile_time}s"
